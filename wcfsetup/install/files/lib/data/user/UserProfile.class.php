<?php

namespace wcf\data\user;

use wcf\data\DatabaseObjectDecorator;
use wcf\data\ITitledLinkObject;
use wcf\data\trophy\Trophy;
use wcf\data\trophy\TrophyCache;
use wcf\data\user\avatar\AvatarDecorator;
use wcf\data\user\avatar\DefaultAvatar;
use wcf\data\user\avatar\IUserAvatar;
use wcf\data\user\avatar\UserAvatar;
use wcf\data\user\cover\photo\DefaultUserCoverPhoto;
use wcf\data\user\cover\photo\IUserCoverPhoto;
use wcf\data\user\cover\photo\UserCoverPhoto;
use wcf\data\user\group\UserGroup;
use wcf\data\user\ignore\UserIgnore;
use wcf\data\user\online\UserOnline;
use wcf\data\user\option\ViewableUserOption;
use wcf\data\user\rank\UserRank;
use wcf\system\cache\builder\UserGroupPermissionCacheBuilder;
use wcf\system\cache\builder\UserRankCacheBuilder;
use wcf\system\cache\runtime\UserProfileRuntimeCache;
use wcf\system\database\util\PreparedStatementConditionBuilder;
use wcf\system\email\Mailbox;
use wcf\system\event\EventHandler;
use wcf\system\exception\ImplementationException;
use wcf\system\user\signature\SignatureCache;
use wcf\system\user\storage\UserStorageHandler;
use wcf\system\WCF;
use wcf\util\DateUtil;
use wcf\util\StringUtil;

/**
 * Decorates the user object and provides functions to retrieve data for user profiles.
 *
 * @author  Marcel Werk
 * @copyright   2001-2019 WoltLab GmbH
 * @license GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 *
 * @method  User    getDecoratedObject()
 * @mixin   User
 */
class UserProfile extends DatabaseObjectDecorator implements ITitledLinkObject
{
    /**
     * @inheritDoc
     */
    protected static $baseClass = User::class;

    /**
     * list of ignored user ids
     * @var int[]
     */
    protected $ignoredUserIDs;

    /**
     * list of user ids that are ignoring this user
     * @var int[]
     */
    protected $ignoredByUserIDs;

    /**
     * list of follower user ids
     * @var int[]
     */
    protected $followerUserIDs;

    /**
     * list of following user ids
     * @var int[]
     */
    protected $followingUserIDs;

    /**
     * @var AvatarDecorator
     */
    protected $avatar;

    /**
     * user rank object
     * @var UserRank
     * @deprecated 6.1 use `->getRank()` instead
     */
    protected $rank;

    /**
     * age of this user
     * @var int
     */
    protected $__age;

    /**
     * group data and permissions
     * @var mixed[][]
     */
    protected $groupData;

    /**
     * current location of this user.
     * @var string
     */
    protected $currentLocation;

    /**
     * user cover photo
     * @var UserCoverPhoto
     */
    protected $coverPhoto;

    const GENDER_MALE = 1;

    const GENDER_FEMALE = 2;

    const GENDER_OTHER = 3;

    const ACCESS_EVERYONE = 0;

    const ACCESS_REGISTERED = 1;

    const ACCESS_FOLLOWING = 2;

    const ACCESS_NOBODY = 3;

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->getDecoratedObject()->__toString();
    }

    /**
     * Returns a list of all user ids being followed by current user.
     *
     * @return  int[]
     */
    public function getFollowingUsers()
    {
        if ($this->followingUserIDs === null) {
            $this->followingUserIDs = [];

            if ($this->userID) {
                // get ids
                $data = UserStorageHandler::getInstance()->getField('followingUserIDs', $this->userID);

                // cache does not exist or is outdated
                if ($data === null) {
                    $sql = "SELECT  followUserID
                            FROM    wcf" . WCF_N . "_user_follow
                            WHERE   userID = ?";
                    $statement = WCF::getDB()->prepareStatement($sql);
                    $statement->execute([$this->userID]);
                    $this->followingUserIDs = $statement->fetchAll(\PDO::FETCH_COLUMN);

                    // update storage data
                    UserStorageHandler::getInstance()->update(
                        $this->userID,
                        'followingUserIDs',
                        \serialize($this->followingUserIDs)
                    );
                } else {
                    $this->followingUserIDs = \unserialize($data);
                }
            }
        }

        return $this->followingUserIDs;
    }

    /**
     * Returns a list of user ids following current user.
     *
     * @return  int[]
     */
    public function getFollowers()
    {
        if ($this->followerUserIDs === null) {
            $this->followerUserIDs = [];

            if ($this->userID) {
                // get ids
                $data = UserStorageHandler::getInstance()->getField('followerUserIDs', $this->userID);

                // cache does not exist or is outdated
                if ($data === null) {
                    $sql = "SELECT  userID
                            FROM    wcf" . WCF_N . "_user_follow
                            WHERE   followUserID = ?";
                    $statement = WCF::getDB()->prepareStatement($sql);
                    $statement->execute([$this->userID]);
                    $this->followerUserIDs = $statement->fetchAll(\PDO::FETCH_COLUMN);

                    // update storage data
                    UserStorageHandler::getInstance()->update(
                        $this->userID,
                        'followerUserIDs',
                        \serialize($this->followerUserIDs)
                    );
                } else {
                    $this->followerUserIDs = \unserialize($data);
                }
            }
        }

        return $this->followerUserIDs;
    }

    /**
     * Returns a list of ignored user ids.
     *
     * @param  ?int  $type One of the UserIgnore::TYPE_* constants.
     * @return  int[]
     */
    public function getIgnoredUsers(?int $type = null)
    {
        if ($this->ignoredUserIDs === null) {
            $this->ignoredUserIDs = [];

            if ($this->userID) {
                // get ids
                $data = UserStorageHandler::getInstance()->getField('ignoredUserIDs', $this->userID);

                // cache does not exist or is outdated
                if ($data === null) {
                    $sql = "SELECT  ignoreUserID, type
                            FROM    wcf" . WCF_N . "_user_ignore
                            WHERE   userID = ?";
                    $statement = WCF::getDB()->prepareStatement($sql);
                    $statement->execute([$this->userID]);
                    $this->ignoredUserIDs = $statement->fetchMap('ignoreUserID', 'type');

                    // update storage data
                    UserStorageHandler::getInstance()->update(
                        $this->userID,
                        'ignoredUserIDs',
                        \serialize($this->ignoredUserIDs)
                    );
                } else {
                    $this->ignoredUserIDs = \unserialize($data);
                }
            }
        }

        return \array_keys(\array_filter($this->ignoredUserIDs, static function ($userType) use ($type) {
            if ($type === null) {
                return true;
            } elseif ($type === UserIgnore::TYPE_BLOCK_DIRECT_CONTACT) {
                return \in_array($userType, [UserIgnore::TYPE_BLOCK_DIRECT_CONTACT, UserIgnore::TYPE_HIDE_MESSAGES]);
            } elseif ($type === UserIgnore::TYPE_HIDE_MESSAGES) {
                return $userType == UserIgnore::TYPE_HIDE_MESSAGES;
            } else {
                return false;
            }
        }));
    }

    /**
     * Returns a list of user ids that are ignoring this user.
     *
     * @return  int[]
     */
    public function getIgnoredByUsers()
    {
        if ($this->ignoredByUserIDs === null) {
            $this->ignoredByUserIDs = [];

            if ($this->userID) {
                // get ids
                $data = UserStorageHandler::getInstance()->getField('ignoredByUserIDs', $this->userID);

                // cache does not exist or is outdated
                if ($data === null) {
                    $sql = "SELECT  userID, type
                            FROM    wcf" . WCF_N . "_user_ignore
                            WHERE   ignoreUserID = ?";
                    $statement = WCF::getDB()->prepareStatement($sql);
                    $statement->execute([$this->userID]);
                    $this->ignoredByUserIDs = $statement->fetchMap('userID', 'type');

                    // update storage data
                    UserStorageHandler::getInstance()->update(
                        $this->userID,
                        'ignoredByUserIDs',
                        \serialize($this->ignoredByUserIDs)
                    );
                } else {
                    $this->ignoredByUserIDs = \unserialize($data);
                }
            }
        }

        return \array_keys($this->ignoredByUserIDs);
    }

    /**
     * Returns true if current user is following given user id.
     *
     * @param int $userID
     * @return  bool
     */
    public function isFollowing($userID)
    {
        return \in_array($userID, $this->getFollowingUsers());
    }

    /**
     * Returns true if given user ids follows current user.
     *
     * @param int $userID
     * @return  bool
     */
    public function isFollower($userID)
    {
        return \in_array($userID, $this->getFollowers());
    }

    /**
     * Returns true if given user is ignored.
     *
     * @param int $userID
     * @param  ?int  $type One of the UserIgnore::TYPE_* constants.
     * @return  bool
     */
    public function isIgnoredUser($userID, ?int $type = null)
    {
        return \in_array($userID, $this->getIgnoredUsers($type));
    }

    /**
     * Returns true if the given user ignores the current user.
     *
     * @param int $userID
     * @return  bool
     */
    public function isIgnoredByUser($userID)
    {
        return \in_array($userID, $this->getIgnoredByUsers());
    }

    /**
     * Returns the user's avatar.
     *
     * @return  AvatarDecorator
     */
    public function getAvatar()
    {
        if ($this->avatar === null) {
            if (!$this->disableAvatar) {
                if ($this->canSeeAvatar()) {
                    if ($this->avatarID) {
                        if (!$this->fileHash) {
                            $data = UserStorageHandler::getInstance()->getField('avatar', $this->userID);
                            if ($data === null) {
                                $this->avatar = new UserAvatar($this->avatarID);
                                UserStorageHandler::getInstance()->update(
                                    $this->userID,
                                    'avatar',
                                    \serialize($this->avatar)
                                );
                            } else {
                                $this->avatar = \unserialize($data);
                            }
                        } else {
                            $this->avatar = new UserAvatar(null, $this->getDecoratedObject()->data);
                        }
                    } else {
                        $parameters = ['avatar' => null];
                        EventHandler::getInstance()->fireAction($this, 'getAvatar', $parameters);

                        if ($parameters['avatar'] !== null) {
                            if (!($parameters['avatar'] instanceof IUserAvatar)) {
                                throw new ImplementationException(
                                    \get_class($parameters['avatar']),
                                    IUserAvatar::class
                                );
                            }

                            $this->avatar = $parameters['avatar'];
                        }
                    }
                }
            }

            // use default avatar
            if ($this->avatar === null) {
                $this->avatar = new DefaultAvatar($this->username ?: '');
            }

            $this->avatar = new AvatarDecorator($this->avatar);
        }

        return $this->avatar;
    }

    /**
     * Returns true if the active user can view the avatar of this user.
     *
     * @return  bool
     */
    public function canSeeAvatar()
    {
        return
            WCF::getUser()->userID == $this->userID
            || WCF::getSession()->getPermission('user.profile.avatar.canSeeAvatars')
            || (($pending = WCF::getSession()->getPendingUserChange()) && $pending->userID == $this->userID);
    }

    /**
     * Returns the user's cover photo.
     *
     * @param bool $isACP override ban on cover photo
     * @return      IUserCoverPhoto
     */
    public function getCoverPhoto($isACP = false)
    {
        if ($this->coverPhoto === null) {
            if ($this->coverPhotoHash) {
                if ($isACP || !$this->disableCoverPhoto) {
                    if ($this->canSeeCoverPhoto()) {
                        $this->coverPhoto = new UserCoverPhoto(
                            $this->userID,
                            $this->coverPhotoHash,
                            $this->coverPhotoExtension,
                            $this->coverPhotoHasWebP
                        );
                    }
                }
            }

            // use default cover photo
            if ($this->coverPhoto === null) {
                $this->coverPhoto = new DefaultUserCoverPhoto();
            }
        }

        return $this->coverPhoto;
    }

    /**
     * Returns true if the active user can view the cover photo of this user.
     *
     * @return      bool
     */
    public function canSeeCoverPhoto()
    {
        return WCF::getUser()->userID == $this->userID || WCF::getSession()->getPermission('user.profile.coverPhoto.canSeeCoverPhotos');
    }

    /**
     * Returns true if this user is currently online.
     *
     * @return  bool
     */
    public function isOnline()
    {
        if ($this->getLastActivityTime() > (TIME_NOW - USER_ONLINE_TIMEOUT) && $this->canViewOnlineStatus()) {
            return true;
        }

        return false;
    }

    /**
     * Returns true if the active user can view the online status of this user.
     *
     * @return  bool
     */
    public function canViewOnlineStatus()
    {
        return WCF::getUser()->userID == $this->userID
            || WCF::getSession()->getPermission('admin.user.canViewInvisible')
            || $this->isAccessible('canViewOnlineStatus');
    }

    /**
     * Returns the current location of this user.
     *
     * @return  string
     */
    public function getCurrentLocation()
    {
        if ($this->currentLocation === null) {
            $userOnline = new UserOnline($this->getDecoratedObject());
            $userOnline->setLocation();

            $this->currentLocation = $userOnline->getLocation();
        }

        return $this->currentLocation;
    }

    /**
     * Returns the special trophies for the user.
     *
     * @return  Trophy[]
     */
    public function getSpecialTrophies()
    {
        $specialTrophies = UserStorageHandler::getInstance()->getField('specialTrophies', $this->userID);

        if ($specialTrophies === null) {
            // load special trophies for the user
            $sql = "SELECT  trophyID
                    FROM    wcf" . WCF_N . "_user_special_trophy
                    WHERE   userID = ?";
            $statement = WCF::getDB()->prepareStatement($sql);
            $statement->execute([$this->userID]);
            $specialTrophies = $statement->fetchAll(\PDO::FETCH_COLUMN);

            UserStorageHandler::getInstance()->update($this->userID, 'specialTrophies', \serialize($specialTrophies));
        } else {
            $specialTrophies = \unserialize($specialTrophies);
        }

        // check if the user has the permission to store these number of trophies,
        // otherwise, delete the last trophies
        if (\count($specialTrophies) > $this->getPermission('user.profile.trophy.maxUserSpecialTrophies')) {
            $trophyDeleteIDs = [];
            while (\count($specialTrophies) > $this->getPermission('user.profile.trophy.maxUserSpecialTrophies')) {
                $trophyDeleteIDs[] = \array_pop($specialTrophies);
            }

            $conditionBuilder = new PreparedStatementConditionBuilder();
            $conditionBuilder->add('userID = ?', [$this->userID]);
            $conditionBuilder->add('trophyID IN (?)', [$trophyDeleteIDs]);

            // reset the user special trophies
            $sql = "DELETE FROM wcf" . WCF_N . "_user_special_trophy
                    " . $conditionBuilder;
            $statement = WCF::getDB()->prepareStatement($sql);
            $statement->execute($conditionBuilder->getParameters());

            UserStorageHandler::getInstance()->update($this->userID, 'specialTrophies', \serialize($specialTrophies));
        }
        $trophies = TrophyCache::getInstance()->getTrophiesByID($specialTrophies);

        $filteredTrophies = \array_filter($trophies);
        if ($filteredTrophies !== $trophies) {
            // One or more trophies no longer exists, remove them from the return
            // value and force a cache reset.
            $trophies = $filteredTrophies;

            UserStorageHandler::getInstance()->reset([$this->userID], 'specialTrophies');
        }

        Trophy::sort($trophies, 'showOrder');

        return $trophies;
    }

    /**
     * Prepares the special trophies for the given user ids.
     *
     * @param int[] $userIDs
     * @since       5.2
     */
    public static function prepareSpecialTrophies(array $userIDs)
    {
        UserProfileRuntimeCache::getInstance()->cacheObjectIDs($userIDs);
        UserStorageHandler::getInstance()->loadStorage($userIDs);
        $storageData = UserStorageHandler::getInstance()->getStorage($userIDs, 'specialTrophies');

        $rebuildUserIDs = $deleteSpecialTrophyIDs = [];
        foreach ($storageData as $userID => $datum) {
            if ($datum === null) {
                $rebuildUserIDs[] = $userID;
            } else {
                $specialTrophies = \unserialize($datum);

                // check if the user has the permission to store these number of trophies,
                // otherwise, delete the last trophies
                if (\count($specialTrophies) > UserProfileRuntimeCache::getInstance()->getObject($userID)->getPermission('user.profile.trophy.maxUserSpecialTrophies')) {
                    $deleteSpecialTrophyIDs[$userID] = [];
                    while (\count($specialTrophies) > UserProfileRuntimeCache::getInstance()->getObject($userID)->getPermission('user.profile.trophy.maxUserSpecialTrophies')) {
                        $deleteSpecialTrophyIDs[$userID] = \array_pop($specialTrophies);
                    }

                    UserStorageHandler::getInstance()->update($userID, 'specialTrophies', \serialize($specialTrophies));
                }
            }
        }

        if (!empty($rebuildUserIDs)) {
            $conditionBuilder = new PreparedStatementConditionBuilder();
            $conditionBuilder->add('userID IN (?)', [$rebuildUserIDs]);

            $sql = "SELECT  userID, trophyID
                    FROM    wcf" . WCF_N . "_user_special_trophy
                    " . $conditionBuilder;
            $statement = WCF::getDB()->prepareStatement($sql);
            $statement->execute($conditionBuilder->getParameters());

            $data = \array_combine($rebuildUserIDs, \array_fill(0, \count($rebuildUserIDs), []));
            while ($row = $statement->fetchArray()) {
                $data[$row['userID']][] = $row['trophyID'];
            }

            foreach ($data as $userID => $trophyIDs) {
                UserStorageHandler::getInstance()->update($userID, 'specialTrophies', \serialize($trophyIDs));
            }
        }

        if (!empty($deleteSpecialTrophyIDs)) {
            $conditionBuilder = new PreparedStatementConditionBuilder(true, 'OR');
            foreach ($deleteSpecialTrophyIDs as $userID => $trophyIDs) {
                $conditionBuilder->add('(userID = ? AND trophyID IN (?))', [$userID, $trophyIDs]);
            }

            $sql = "DELETE FROM wcf" . WCF_N . "_user_special_trophy
                    " . $conditionBuilder;
            $statement = WCF::getDB()->prepareStatement($sql);
            $statement->execute($conditionBuilder->getParameters());
        }
    }

    /**
     * Returns the last activity time.
     *
     * @return  int
     */
    public function getLastActivityTime()
    {
        return \max($this->lastActivityTime, $this->sessionLastActivityTime);
    }

    /**
     * Returns a new user profile object.
     *
     * @param int $userID
     * @return  UserProfile
     * @deprecated  3.0, use UserProfileRuntimeCache::getObject()
     */
    public static function getUserProfile($userID)
    {
        return UserProfileRuntimeCache::getInstance()->getObject($userID);
    }

    /**
     * Returns a list of user profiles.
     *
     * @param int[] $userIDs
     * @return  UserProfile[]
     * @deprecated  3.0, use UserProfileRuntimeCache::getObjects()
     */
    public static function getUserProfiles(array $userIDs)
    {
        $users = UserProfileRuntimeCache::getInstance()->getObjects($userIDs);

        // this method does not return null for non-existing user profiles
        foreach ($users as $userID => $user) {
            if ($user === null) {
                unset($users[$userID]);
            }
        }

        return $users;
    }

    /**
     * Returns the user profile of the user with the given name.
     *
     * @param string $username
     * @return  UserProfile
     */
    public static function getUserProfileByUsername($username)
    {
        $users = self::getUserProfilesByUsername([$username]);

        return $users[$username];
    }

    /**
     * Returns the user profiles of the users with the given names.
     *
     * @param string[] $usernames
     * @return  UserProfile[]
     */
    public static function getUserProfilesByUsername(array $usernames)
    {
        $users = [];

        // save case sensitive usernames
        $caseSensitiveUsernames = [];
        foreach ($usernames as &$username) {
            $tmp = \mb_strtolower($username);
            $caseSensitiveUsernames[$tmp] = $username;
            $username = $tmp;
        }
        unset($username);

        // check cache
        $userProfiles = UserProfileRuntimeCache::getInstance()->getCachedObjects();
        foreach ($usernames as $index => $username) {
            foreach ($userProfiles as $user) {
                if ($user === null) {
                    continue;
                }

                if (\mb_strtolower($user->username) === $username) {
                    $users[$username] = $user;
                    unset($usernames[$index]);
                }
            }
        }

        if (!empty($usernames)) {
            $userList = new UserProfileList();
            $userList->getConditionBuilder()->add("user_table.username IN (?)", [$usernames]);
            $userList->readObjects();

            foreach ($userList as $user) {
                $users[\mb_strtolower($user->username)] = $user;
                UserProfileRuntimeCache::getInstance()->addUserProfile($user);
            }

            foreach ($usernames as $username) {
                if (!isset($users[$username])) {
                    $users[$username] = null;
                }
            }
        }

        // revert usernames to original case
        foreach ($users as $username => $user) {
            unset($users[$username]);
            if (isset($caseSensitiveUsernames[$username])) {
                $users[$caseSensitiveUsernames[$username]] = $user;
            }
        }

        return $users;
    }

    /**
     * Returns true if current user fulfills the required permissions.
     */
    public function isAccessible(string $name, ?int $userID = null): bool
    {
        if ($userID === null) {
            $userID = WCF::getUser()->userID;
        }
        $data = ['result' => true, 'name' => $name, 'userID' => $userID];

        switch ($this->{$name}) {
            case self::ACCESS_EVERYONE:
                $data['result'] = true;
                break;

            case self::ACCESS_REGISTERED:
                $data['result'] = ($userID ? true : false);
                break;

            case self::ACCESS_FOLLOWING:
                $result = false;
                if ($userID) {
                    if ($userID == $this->userID) {
                        $result = true;
                    } elseif ($this->isFollowing($userID)) {
                        $result = true;
                    }
                }

                $data['result'] = $result;
                break;

            case self::ACCESS_NOBODY:
                $data['result'] = false;
                break;
        }

        EventHandler::getInstance()->fireAction($this, 'isAccessible', $data);

        return $data['result'];
    }

    /**
     * Returns true if current user profile is protected.
     *
     * @return  bool
     */
    public function isProtected()
    {
        return !WCF::getSession()->getPermission('admin.general.canViewPrivateUserOptions') && !$this->isAccessible('canViewProfile') && $this->userID != WCF::getUser()->userID;
    }

    /**
     * Returns the age of this user.
     *
     * @param int $year
     * @return  int
     */
    public function getAge($year = null)
    {
        $showYear = $this->birthdayShowYear || WCF::getSession()->getPermission('admin.general.canViewPrivateUserOptions');

        if ($year !== null) {
            if ($showYear) {
                $birthdayYear = 0;
                $value = \explode('-', $this->birthday);
                if (isset($value[0])) {
                    $birthdayYear = \intval($value[0]);
                }
                if ($birthdayYear) {
                    return $year - $birthdayYear;
                }
            }

            return 0;
        } else {
            if ($this->__age === null) {
                if ($this->birthday && $showYear) {
                    $this->__age = DateUtil::getAge($this->birthday);
                } else {
                    $this->__age = 0;
                }
            }

            return $this->__age;
        }
    }

    /**
     * Returns the formatted birthday of this user.
     *
     * @param int $year
     * @return  string
     */
    public function getBirthday($year = null)
    {
        // split date
        $birthdayYear = $month = $day = 0;
        $value = \explode('-', $this->birthday);
        if (isset($value[0])) {
            $birthdayYear = \intval($value[0]);
        }
        if (isset($value[1])) {
            $month = \intval($value[1]);
        }
        if (isset($value[2])) {
            $day = \intval($value[2]);
        }

        if (!$month || !$day) {
            return '';
        }

        $showYear = $this->birthdayShowYear || WCF::getSession()->getPermission('admin.general.canViewPrivateUserOptions');

        $d = new \DateTimeImmutable($this->birthday, WCF::getUser()->getTimeZone());
        $dateFormat = (($showYear && $birthdayYear) ? WCF::getLanguage()->get(DateUtil::DATE_FORMAT) : \str_replace(
            'Y',
            '',
            WCF::getLanguage()->get(DateUtil::DATE_FORMAT)
        ));
        $birthday = DateUtil::localizeDate($d->format($dateFormat), $dateFormat, WCF::getLanguage());

        if ($showYear) {
            $age = $this->getAge($year);
            if ($age > 0) {
                $birthday .= ' (' . $age . ')';
            }
        }

        return $birthday;
    }

    /**
     * Returns the age of user account in days.
     *
     * @return  int
     */
    public function getProfileAge()
    {
        return (TIME_NOW - $this->registrationDate) / 86400;
    }

    /**
     * Returns the value of the permission with the given name.
     *
     * @param string $permission
     * @return  mixed       permission value
     */
    public function getPermission($permission)
    {
        if ($this->groupData === null) {
            $this->loadGroupData();
        }

        if (!isset($this->groupData[$permission])) {
            return false;
        }

        return $this->groupData[$permission];
    }

    /**
     * Returns true if a permission was set to 'Never'. This is required to preserve
     * compatibility, while preventing ACLs from overruling a 'Never' setting.
     *
     * @param string $permission
     * @return      bool
     */
    public function getNeverPermission($permission)
    {
        $this->loadGroupData();

        return isset($this->groupData['__never'][$permission]);
    }

    /**
     * Returns the user title of this user.
     *
     * @return  string
     */
    public function getUserTitle()
    {
        if ($this->userTitle) {
            return $this->userTitle;
        }
        if ($this->getRank() && $this->getRank()->showTitle()) {
            return WCF::getLanguage()->get($this->getRank()->rankTitle);
        }

        return '';
    }

    public function getRank(): ?UserRank
    {
        if (!\MODULE_USER_RANK) {
            return null;
        }

        if (!$this->rankID) {
            return null;
        }

        return UserRankCacheBuilder::getInstance()->getRank($this->rankID);
    }

    /**
     * Loads group data from cache.
     */
    protected function loadGroupData()
    {
        $this->groupData = UserGroupPermissionCacheBuilder::getInstance()->getData($this->getGroupIDs());
    }

    /**
     * Returns the old username of this user.
     *
     * @return  string
     */
    public function getOldUsername()
    {
        if ($this->oldUsername) {
            if ($this->lastUsernameChange + PROFILE_SHOW_OLD_USERNAME * 86400 > TIME_NOW) {
                return $this->oldUsername;
            }
        }

        return '';
    }

    /**
     * Returns true if this user can edit his profile.
     *
     * @return  bool
     */
    public function canEditOwnProfile()
    {
        if ($this->pendingActivation() || !$this->getPermission('user.profile.canEditUserProfile')) {
            return false;
        }

        return true;
    }

    /**
     * Returns the encoded email address.
     */
    public function getEncodedEmail(): string
    {
        if ($this->email === '') {
            return '';
        }

        try {
            $mailbox = new Mailbox($this->email);
        } catch (\Throwable) {
            // Skip invalid email addresses.
            return '';
        }

        return StringUtil::encodeAllChars($mailbox->getAddressForMailto());
    }

    /**
     * @deprecated 5.4 This method is unused internally and redundant with `User::getAuthProvider()`.
     */
    public function isConnectedWithFacebook()
    {
        return \str_starts_with($this->authData, 'facebook:');
    }

    /**
     * @deprecated 5.4 This method is unused internally and redundant with `User::getAuthProvider()`.
     */
    public function isConnectedWithGithub()
    {
        return \str_starts_with($this->authData, 'github:');
    }

    /**
     * @deprecated 5.4 This method is unused internally and redundant with `User::getAuthProvider()`.
     */
    public function isConnectedWithGoogle()
    {
        return \str_starts_with($this->authData, 'google:');
    }

    /**
     * @deprecated 5.4 This method is unused internally and redundant with `User::getAuthProvider()`.
     */
    public function isConnectedWithTwitter()
    {
        return \str_starts_with($this->authData, 'twitter:');
    }

    /**
     * Returns 3rd party auth provider name.
     *
     * @return  string
     */
    public function getAuthProvider()
    {
        return $this->getDecoratedObject()->getAuthProvider();
    }

    /**
     * Return true if the user's signature is visible.
     *
     * @return  bool
     */
    public function showSignature()
    {
        if (!MODULE_USER_SIGNATURE) {
            return false;
        }
        if (!$this->signature) {
            return false;
        }
        if ($this->disableSignature) {
            return false;
        }
        if ($this->banned) {
            return false;
        }
        if (WCF::getUser()->userID && !WCF::getUser()->showSignature) {
            return false;
        }

        return true;
    }

    /**
     * Returns the parsed signature.
     *
     * @return  string
     */
    public function getSignature()
    {
        return SignatureCache::getInstance()->getSignature($this->getDecoratedObject());
    }

    /**
     * Returns the formatted value of the user option with the given name.
     *
     * @param string $name
     * @return  mixed
     */
    public function getFormattedUserOption($name)
    {
        // get value
        $value = $this->getUserOption($name);
        if (!$value) {
            return '';
        }

        $option = ViewableUserOption::getUserOption($name);
        if (!$option->isVisible()) {
            return '';
        }
        $option->setOptionValue($this->getDecoratedObject());

        return $option->optionValue;
    }

    /**
     * Returns true, if the active user has access to the user option with the given name.
     *
     * @param string $name
     * @return  bool
     */
    public function isVisibleOption($name)
    {
        $option = ViewableUserOption::getUserOption($name);

        return $option->isVisible();
    }

    /**
     * Returns the formatted username.
     *
     * @return  string
     */
    public function getFormattedUsername()
    {
        $username = StringUtil::encodeHTML($this->username);

        if ($this->userOnlineGroupID) {
            $group = UserGroup::getGroupByID($this->userOnlineGroupID);
            if ($group !== null && $group->userOnlineMarking && $group->userOnlineMarking != '%s') {
                return \str_replace('%s', $username, $group->userOnlineMarking);
            }
        }

        return $username;
    }

    /**
     * Returns a HTML anchor link pointing to the decorated user.
     *
     * @return  string
     */
    public function getAnchorTag()
    {
        return '<a href="' . $this->getLink() . '" class="userLink" data-object-id="' . $this->userID . '">' . StringUtil::encodeHTML($this->username) . '</a>';
    }

    /**
     * @inheritDoc
     */
    public function getLink(): string
    {
        return $this->getDecoratedObject()->getLink();
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return $this->getDecoratedObject()->getTitle();
    }

    /**
     * Sets the session-based last activity time.
     *
     * @param int $timestamp
     */
    public function setSessionLastActivityTime($timestamp)
    {
        $this->object->data['sessionLastActivityTime'] = $timestamp;
    }

    /**
     * Returns an "empty" user profile object for a guest with the given username.
     *
     * Such objects can also be used in situations where the relevant user has been deleted
     * but their original username is still known.
     *
     * @param string $username
     * @return  UserProfile
     * @since   3.0
     */
    public static function getGuestUserProfile($username)
    {
        return new self(new User(null, ['username' => $username]));
    }

    /**
     * @since 6.1
     */
    public function showTrophyPoints(): bool
    {
        return MODULE_TROPHY
            && WCF::getSession()->getPermission('user.profile.trophy.canSeeTrophies')
            && $this->trophyPoints
            && ($this->isAccessible('canViewTrophies') || $this->userID == WCF::getSession()->userID);
    }
}
