/**
 * @woltlabExcludeBundle all
 */

import UiUserSearchInput from "WoltLabSuite/Core/Ui/User/Search/Input";
import { checkDependencies } from "WoltLabSuite/Core/Form/Builder/Field/Dependency/Manager";
import { getPhrase } from "WoltLabSuite/Core/Language";
import DomUtil from "WoltLabSuite/Core/Dom/Util";
import * as StringUtil from "WoltLabSuite/Core/StringUtil";
import { DatabaseObjectActionResponse } from "WoltLabSuite/Core/Ajax/Data";
import * as Ajax from "WoltLabSuite/Core/Ajax";
import { extend } from "WoltLabSuite/Core/Core";

interface AclOption {
  categoryName: string;
  label: string;
  optionName: string;
}

interface AclValues {
  label: {
    [key: string]: string;
  };
  option: {
    [key: string]: {
      [key: string]: number;
    };
  };
}

interface AjaxResponse extends DatabaseObjectActionResponse {
  returnValues: {
    options: {
      [key: string]: AclOption;
    };
    group: AclValues;
    user: AclValues;
    categories: {
      [key: string]: string;
    };
  };
}

export = class AclList {
  readonly #categoryName: string | undefined;
  readonly #container: HTMLElement;
  readonly #aclList: HTMLUListElement;
  readonly #permissionList: HTMLDivElement;
  readonly #searchInput: HTMLInputElement;
  readonly #objectID: number;
  readonly #objectTypeID: number;
  readonly #aclValuesFieldName: string;
  readonly #search: UiUserSearchInput;
  #values: {
    [key: string]: {
      [key: string]: {
        [key: string]: number;
      };
    };
  };

  constructor(
    containerSelector: string,
    objectTypeID: number,
    categoryName: string | undefined,
    objectID: number,
    includeUserGroups: boolean,
    initialPermissions: AjaxResponse | undefined,
    aclValuesFieldName: string | undefined,
  ) {
    this.#objectID = objectID || 0;
    this.#objectTypeID = objectTypeID;
    this.#categoryName = categoryName;
    if (includeUserGroups === undefined) {
      includeUserGroups = true;
    }
    this.#values = {
      group: {},
      user: {},
    };
    this.#aclValuesFieldName = aclValuesFieldName || "aclValues";

    // bind hidden container
    this.#container = document.querySelector(containerSelector)!;
    DomUtil.hide(this.#container);
    this.#container.classList.add("aclContainer");

    // insert container elements
    const elementContainer = this.#container.querySelector("dd")!;
    this.#aclList = document.createElement("ul");
    this.#aclList.classList.add("aclList");
    elementContainer.appendChild(this.#aclList);

    this.#searchInput = document.createElement("input");
    this.#searchInput.classList.add("aclSearchInput");
    this.#searchInput.type = "text";
    this.#searchInput.classList.add("long");
    this.#searchInput.placeholder = getPhrase("wcf.acl.search." + (!includeUserGroups ? "user." : "") + "description");
    elementContainer.appendChild(this.#searchInput);

    this.#permissionList = document.createElement("div");
    this.#permissionList.classList.add("aclPermissionList");
    DomUtil.hide(this.#permissionList);
    elementContainer.appendChild(this.#permissionList);

    // prepare search input
    this.#search = new UiUserSearchInput(this.#searchInput, {
      callbackSelect: this.addObject.bind(this),
      includeUserGroups: includeUserGroups,
      preventSubmit: true,
    });

    // bind event listener for submit
    const form = this.#container.closest("form")!;
    form.addEventListener("submit", () => {
      this.submit();
    });

    // reset ACL on click
    const resetButton = form.querySelector("input[type=reset]");
    resetButton?.addEventListener("click", () => {
      this.#reset();
    });

    if (initialPermissions) {
      this.#success(initialPermissions);
    } else {
      this.#loadACL();
    }
  }

  public getData() {
    this.#savePermissions();

    return this.#values;
  }

  public addObject(selectedItem: HTMLLIElement): boolean {
    const type = selectedItem.dataset.type!;
    const label = selectedItem.dataset.label!;
    const objectId = selectedItem.dataset.objectId!;

    const listItem = this.#createListItem(objectId, label, type);

    // toggle element
    this.#savePermissions();
    this.#aclList.querySelectorAll(".aclListItem").forEach((element: HTMLLIElement) => {
      element.classList.remove("active");
    });
    listItem.classList.add("active");

    this.#search.addExcludedSearchValues(label);

    this.#select(listItem, false);

    // clear search input
    this.#searchInput.value = "";

    // show permissions
    DomUtil.show(this.#permissionList);

    return false;
  }

  public submit() {
    this.#savePermissions();

    this.#save("group");
    this.#save("user");
  }

  #reset() {
    // reset stored values
    this.#values = {
      group: {},
      user: {},
    };

    // remove entries
    this.#aclList.innerHTML = "";
    this.#searchInput.value = "";

    // deselect all input elements
    DomUtil.hide(this.#permissionList);
    this.#permissionList.querySelectorAll("input[type=radio]").forEach((inputElement: HTMLInputElement) => {
      inputElement.checked = false;
    });
  }

  #loadACL() {
    Ajax.apiOnce({
      data: {
        actionName: "loadAll",
        className: "wcf\\data\\acl\\option\\ACLOptionAction",
        parameters: {
          categoryName: this.#categoryName,
          objectID: this.#objectID,
          objectTypeID: this.#objectTypeID,
        },
      },
      success: (data: AjaxResponse) => {
        this.#success(data);
      },
    });
  }

  #createListItem(objectID: string, label: string, type: string): HTMLLIElement {
    const html = `<fa-icon size="16" name="${type === "group" ? "users" : "user"}" solid></fa-icon>
        <span class="aclLabel">${StringUtil.escapeHTML(label)}</span>
        <button type="button" class="aclItemDeleteButton jsTooltip" title="${getPhrase("wcf.global.button.delete")}">
          <fa-icon size="16" name="xmark" solid></fa-icon>
        </button>`;
    const listItem = document.createElement("li");
    listItem.classList.add("aclListItem");
    listItem.innerHTML = html;
    listItem.dataset.objectId = objectID;
    listItem.dataset.type = type;
    listItem.dataset.label = label;
    listItem.addEventListener("click", () => {
      if (listItem.classList.contains("active")) {
        return;
      }

      this.#select(listItem, true);
    });

    const deleteButton = listItem.querySelector(".aclItemDeleteButton") as HTMLButtonElement;
    deleteButton.addEventListener("click", (e) => {
      e.stopPropagation();
      this.#removeItem(listItem);
    });

    this.#aclList.appendChild(listItem);

    return listItem;
  }

  #removeItem(listItem: HTMLLIElement) {
    this.#savePermissions();

    const type = listItem.dataset.type!;
    const objectID = listItem.dataset.objectId!;

    this.#search.removeExcludedSearchValues(listItem.dataset.label!);
    listItem.remove();

    // remove stored data
    if (this.#values[type][objectID]) {
      delete this.#values[type][objectID];
    }

    // try to select something else
    this.#selectFirstEntry();
  }

  #selectFirstEntry() {
    const listItem = this.#aclList.querySelector<HTMLElement>(".aclListItem");
    if (listItem) {
      this.#select(listItem, false);
    } else {
      this.#reset();
    }
  }

  #success(data: AjaxResponse) {
    if (Object.keys(data.returnValues.options).length === 0) {
      return;
    }

    const header = document.createElement("div");
    header.classList.add("aclHeader");
    header.innerHTML = `<span class="aclHeaderSpan aclHeaderInherited">${getPhrase("wcf.acl.option.inherited")}</span>
        <span class="aclHeaderSpan aclHeaderGrant">${getPhrase("wcf.acl.option.grant")}</span>
        <span class="aclHeaderSpan aclHeaderDeny">${getPhrase("wcf.acl.option.deny")}</span>`;

    this.#permissionList.appendChild(header);

    // prepare options
    const structure: { [key: string]: HTMLDivElement[] } = {};
    for (const [optionID, option] of Object.entries(data.returnValues.options)) {
      const listItem = document.createElement("div");
      listItem.classList.add("aclOption", "aclPermissionListItem");

      listItem.innerHTML = `<span class="aclOptionTitle">${StringUtil.escapeHTML(option.label)}</span>
        <label for="inherited${optionID}" class="inherited aclOptionInputLabel">
          <input type="radio" id="inherited${optionID}">
        </label>
        <label for="grant${optionID}" class="grant aclOptionInputLabel">
          <input type="radio" id="grant${optionID}">
        </label>
        <label for="deny${optionID}" class="deny aclOptionInputLabel">
          <input type="radio" id="deny${optionID}">
        </label>`;
      listItem.dataset.optionId = optionID;
      listItem.dataset.optionName = option.optionName;

      const grantPermission = listItem.querySelector(`#grant${optionID}`) as HTMLInputElement;
      const denyPermission = listItem.querySelector(`#deny${optionID}`) as HTMLInputElement;
      const inheritedPermission = listItem.querySelector(`#inherited${optionID}`) as HTMLInputElement;

      grantPermission.dataset.type = "grant";
      grantPermission.dataset.optionId = optionID;
      grantPermission.addEventListener("change", this.#change.bind(this));

      denyPermission.dataset.type = "deny";
      denyPermission.dataset.optionId = optionID;
      denyPermission.addEventListener("change", this.#change.bind(this));

      inheritedPermission.dataset.type = "inherited";
      inheritedPermission.dataset.optionId = optionID;
      inheritedPermission.addEventListener("change", this.#change.bind(this));

      if (!structure[option.categoryName]) {
        structure[option.categoryName] = [];
      }

      if (option.categoryName === "") {
        this.#permissionList.appendChild(listItem);
      } else {
        structure[option.categoryName].push(listItem);
      }
    }

    if (Object.keys(structure).length > 0) {
      for (const [categoryName, listItems] of Object.entries(structure)) {
        if (data.returnValues.categories[categoryName]) {
          const category = document.createElement("div");
          category.classList.add("aclCategory", "aclPermissionListItem");
          category.innerText = StringUtil.escapeHTML(data.returnValues.categories[categoryName]);
          this.#permissionList.appendChild(category);
        }

        listItems.forEach((listItem) => {
          this.#permissionList.appendChild(listItem);
        });
      }
    }

    // set data
    this.#parseData(data, "group");
    this.#parseData(data, "user");

    // show container
    DomUtil.show(this.#container);

    // Because the container might have been hidden before, we must ensure that
    // form builder field dependencies are checked again to avoid having ACL
    // form fields not being shown in form builder forms.
    checkDependencies();

    // pre-select an entry
    this.#selectFirstEntry();
  }

  #parseData(data: AjaxResponse, type: string) {
    const values = data.returnValues[type];
    if (Array.isArray(values) || Object.keys(values.option).length === 0) {
      return;
    }

    // add list items
    for (const typeID in values.label) {
      this.#createListItem(typeID, values.label[typeID], type);

      this.#search.addExcludedSearchValues(values.label[typeID]);
    }

    // add options
    this.#values[type] = values.option;
  }

  #select(listItem: HTMLElement, savePermissions: boolean) {
    // save previous permissions
    if (savePermissions) {
      this.#savePermissions();
    }

    // switch active item
    this.#aclList.querySelectorAll(".aclListItem").forEach((li: HTMLElement) => {
      li.classList.remove("active");
    });
    listItem.classList.add("active");

    // apply permissions for current item
    this.#setupPermissions(listItem.dataset.type!, listItem.dataset.objectId!);
  }

  #change(event: MouseEvent) {
    const checkbox = event.currentTarget as HTMLInputElement;
    const optionID = checkbox.dataset.optionId!;
    const type = checkbox.dataset.type!;

    if (checkbox.checked) {
      switch (type) {
        case "grant":
          (document.getElementById("deny" + optionID)! as HTMLInputElement).checked = false;
          (document.getElementById("inherited" + optionID)! as HTMLInputElement).checked = false;
          break;
        case "deny":
          (document.getElementById("grant" + optionID)! as HTMLInputElement).checked = false;
          (document.getElementById("inherited" + optionID)! as HTMLInputElement).checked = false;
          break;
        case "inherited":
          (document.getElementById("deny" + optionID)! as HTMLInputElement).checked = false;
          (document.getElementById("grant" + optionID)! as HTMLInputElement).checked = false;
          break;
      }
    }
  }

  #setupPermissions(type: string, objectID: string) {
    // reset all checkboxes to default value
    this.#permissionList.querySelectorAll("input[type='radio']").forEach((inputElement: HTMLInputElement) => {
      inputElement.checked = inputElement.dataset.type === "inherited";
    });

    // use stored permissions if applicable
    if (this.#values[type] && this.#values[type][objectID]) {
      for (const optionID in this.#values[type][objectID]) {
        if (this.#values[type][objectID][optionID] == 1) {
          const option = document.getElementById("grant" + optionID) as HTMLInputElement;
          option.checked = true;
          option.dispatchEvent(new Event("change"));
        } else {
          const option = document.getElementById("deny" + optionID) as HTMLInputElement;
          option.checked = true;
          option.dispatchEvent(new Event("change"));
        }
      }
    }

    // show permissions
    DomUtil.show(this.#permissionList);
  }

  #savePermissions() {
    // get active object
    const activeObject = this.#aclList.querySelector(".aclListItem.active") as HTMLElement;
    if (!activeObject) {
      return;
    }

    const objectID = activeObject.dataset.objectId!;
    const type = activeObject.dataset.type!;

    // clear old values
    this.#values[type][objectID] = {};
    this.#permissionList.querySelectorAll("input[type='radio']").forEach((checkbox: HTMLInputElement) => {
      if (checkbox.dataset.type === "inherited") {
        return;
      }

      const optionValue = checkbox.dataset.type === "deny" ? 0 : 1;
      const optionID = checkbox.dataset.optionId!;

      if (checkbox.checked) {
        // store value
        this.#values[type][objectID][optionID] = optionValue;

        // reset value afterwards
        checkbox.checked = false;
      } else if (
        this.#values[type] &&
        this.#values[type][objectID] &&
        this.#values[type][objectID][optionID] &&
        this.#values[type][objectID][optionID] == optionValue
      ) {
        delete this.#values[type][objectID][optionID];
      }
    });
  }

  #save(type: string) {
    const form = this.#container.closest("form")!;
    const name = this.#aclValuesFieldName + "[" + type + "]";
    let input = form.querySelector<HTMLInputElement>("input[name='" + name + "']");
    if (input) {
      // combine json values
      input.value = JSON.stringify(extend(JSON.parse(input.value), this.#values[type]));
    } else {
      input = document.createElement("input");
      input.type = "hidden";
      input.name = name;
      input.value = JSON.stringify(this.#values[type]);
      form.appendChild(input);
    }
  }
};
