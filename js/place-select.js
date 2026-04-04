(function ($) {
  if (typeof wc_city_select_params === "undefined") {
    return false;
  }

  function getSelectText(key, fallback) {
    if (
      typeof wc_country_select_params !== "undefined" &&
      wc_country_select_params[key]
    ) {
      return wc_country_select_params[key];
    }

    return fallback;
  }

  function getEnhancedSelectFormatString() {
    return {
      formatMatches: function (matches) {
        if (1 === matches) {
          return getSelectText("i18n_matches_1", "One result is available.");
        }

        return getSelectText(
          "i18n_matches_n",
          "%qty% results are available.",
        ).replace("%qty%", matches);
      },
      formatNoMatches: function () {
        return getSelectText("i18n_no_matches", "No matches found");
      },
      formatAjaxError: function () {
        return getSelectText("i18n_ajax_error", "Loading failed");
      },
      formatInputTooShort: function (input, min) {
        var number = min - input.length;

        if (1 === number) {
          return getSelectText(
            "i18n_input_too_short_1",
            "Please enter 1 or more characters",
          );
        }

        return getSelectText(
          "i18n_input_too_short_n",
          "Please enter %qty% or more characters",
        ).replace("%qty%", number);
      },
      formatInputTooLong: function (input, max) {
        var number = input.length - max;

        if (1 === number) {
          return getSelectText(
            "i18n_input_too_long_1",
            "Please delete 1 character",
          );
        }

        return getSelectText(
          "i18n_input_too_long_n",
          "Please delete %qty% characters",
        ).replace("%qty%", number);
      },
      formatSelectionTooBig: function (limit) {
        if (1 === limit) {
          return getSelectText(
            "i18n_selection_too_long_1",
            "You can only select 1 item",
          );
        }

        return getSelectText(
          "i18n_selection_too_long_n",
          "You can only select %qty% items",
        ).replace("%qty%", limit);
      },
      formatLoadMore: function () {
        return getSelectText("i18n_load_more", "Loading more results…");
      },
      formatSearching: function () {
        return getSelectText("i18n_searching", "Searching…");
      },
    };
  }

  var citiesJson = String(wc_city_select_params.cities || "{}").replace(
    /&quot;/g,
    '"',
  );
  var cities = {};

  try {
    cities = $.parseJSON(citiesJson);
  } catch (error) {
    cities = {};
  }

  var elBodyDPWoo = $("body");
  var blockCheckoutObserver = null;
  var blockCheckoutRenderQueued = false;
  var blockCheckoutObserverTarget = null;

  function getCityPlaceholder() {
    return wc_city_select_params.i18n_select_city_text || "Select an option…";
  }

  function getCountryPlaces(country) {
    if (!country || !cities || !cities[country]) {
      return null;
    }

    return cities[country];
  }

  function getCitiesForLocation(country, state) {
    var countryPlaces = getCountryPlaces(country);

    if (!countryPlaces) {
      return null;
    }

    if (countryPlaces instanceof Array) {
      return countryPlaces;
    }

    if (state && countryPlaces[state]) {
      return countryPlaces[state];
    }

    return null;
  }

  function countryRequiresState(country) {
    var countryPlaces = getCountryPlaces(country);

    return !!(countryPlaces && !(countryPlaces instanceof Array));
  }

  function cityToInput($citybox) {
    if (!$citybox.length) {
      return;
    }

    if ($citybox.is("input")) {
      $citybox.prop("disabled", false);
      return;
    }

    var inputName = $citybox.attr("name");
    var inputId = $citybox.attr("id");
    var placeholder = $citybox.attr("placeholder");

    $citybox.replaceWith(
      '<input type="text" class="input-text" name="' +
        inputName +
        '" id="' +
        inputId +
        '" placeholder="' +
        placeholder +
        '" />',
    );
  }

  function disableCity($citybox) {
    if (!$citybox.length) {
      return;
    }

    $citybox.val("").change();
    $citybox.prop("disabled", true);
  }

  function cityToSelect($citybox, currentCities) {
    if (!$citybox.length) {
      return;
    }

    var value = $citybox.val();

    $citybox.val("");
    if (!$citybox.is("select")) {
      var inputName = $citybox.attr("name");
      var inputId = $citybox.attr("id");
      var placeholder = $citybox.attr("placeholder");

      $citybox.replaceWith(
        '<select name="' +
          inputName +
          '" id="' +
          inputId +
          '" class="city_select" placeholder="' +
          placeholder +
          '"></select>',
      );
      $citybox = $("#" + inputId);
    } else {
      $citybox.prop("disabled", false);
    }

    var options = "";
    for (var index in currentCities) {
      if (currentCities.hasOwnProperty(index)) {
        var cityName = currentCities[index];
        options += '<option value="' + cityName + '">' + cityName + "</option>";
      }
    }

    $citybox.html(
      '<option value="">' + getCityPlaceholder() + "</option>" + options,
    );

    if ($('option[value="' + value + '"]', $citybox).length) {
      $citybox.val(value).change();
    } else {
      $citybox.val("").change();
    }
  }

  function getClassicCityField() {
    var sendToOtherAddress = $("#ship-to-different-address-checkbox").is(
      ":checked",
    );
    var $citybox = $();

    if (
      sendToOtherAddress === false &&
      $("#billing_city").attr("type") !== "hidden"
    ) {
      $citybox = $("#billing_city");
    } else {
      $citybox = $("#shipping_city");
    }

    if ($citybox.length === 0) {
      $citybox = $("#calc_shipping_city");
    }

    return $citybox;
  }

  function updateClassicCityField(country, state) {
    var $citybox = getClassicCityField();

    if (!$citybox.length) {
      return;
    }

    var currentCities = getCitiesForLocation(country, state);

    if (currentCities instanceof Array) {
      cityToSelect($citybox, currentCities);
      return;
    }

    if (countryRequiresState(country) && !state) {
      disableCity($citybox);
      return;
    }

    cityToInput($citybox);
  }

  function initClassicCitySelectors() {
    elBodyDPWoo.on(
      "country_to_state_changing",
      function (e, country, $container) {
        var $statebox = $container.find(
          "#billing_state, #shipping_state, #calc_shipping_state",
        );
        var state = $statebox.val();

        $(document.body).trigger("state_changing", [
          country,
          state,
          $container,
        ]);
      },
    );

    elBodyDPWoo.on(
      "change",
      "select.state_select, #calc_shipping_state",
      function () {
        var $container = $(this).closest("div");
        var country = $container
          .find("#billing_country, #shipping_country, #calc_shipping_country")
          .val();
        var state = $(this).val();

        $(document.body).trigger("state_changing", [
          country,
          state,
          $container,
        ]);
      },
    );

    elBodyDPWoo.on("state_changing", function (e, country, state) {
      updateClassicCityField(country, state);
    });

    setTimeout(function () {
      var $container = $(this).closest("div");
      var country = $(
        "#billing_country, #shipping_country, #calc_shipping_country",
      ).val();
      var state = $("select.state_select, #calc_shipping_state").val();

      $(document.body).trigger("state_changing", [country, state, $container]);
    }, 500);

    if ($(".cart-collaterals").length && $("#calc_shipping_state").length) {
      var calcObserver = new MutationObserver(function () {
        $("#calc_shipping_state").change();
      });

      calcObserver.observe(document.querySelector(".cart-collaterals"), {
        childList: true,
      });
    }
  }

  function getBlockField(prefix, field) {
    return document.getElementById(prefix + "-" + field);
  }

  function dispatchNativeEvents(el) {
    if (!el) {
      return;
    }

    el.dispatchEvent(new Event("input", { bubbles: true }));
    el.dispatchEvent(new Event("change", { bubbles: true }));
    el.dispatchEvent(new Event("blur", { bubbles: true }));
  }

  function setNativeValue(el, value) {
    if (!el) {
      return;
    }

    var prototype =
      el.tagName === "SELECT"
        ? window.HTMLSelectElement.prototype
        : window.HTMLInputElement.prototype;
    var descriptor = Object.getOwnPropertyDescriptor(prototype, "value");

    if (descriptor && descriptor.set) {
      descriptor.set.call(el, value);
    } else {
      el.value = value;
    }
  }

  function createBlockCitySelectWrapper(prefix, labelText) {
    var wrapper = document.createElement("div");
    wrapper.className =
      "wc-block-components-address-form__city dropi-block-city-select";
    wrapper.id = prefix + "-city-dropi-wrapper";
    wrapper.innerHTML =
      '<div class="wc-blocks-components-select">' +
      '<div class="wc-blocks-components-select__container">' +
      '<label for="' +
      prefix +
      '-city-dropi-select" class="wc-blocks-components-select__label">' +
      labelText +
      "</label>" +
      '<select size="1" class="wc-blocks-components-select__select" id="' +
      prefix +
      '-city-dropi-select"></select>' +
      '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24" class="wc-blocks-components-select__expand" aria-hidden="true" focusable="false"><path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"></path></svg>' +
      "</div>" +
      "</div>";

    return wrapper;
  }

  function getBlockRenderSignature(
    prefix,
    country,
    state,
    currentCities,
    selectedValue,
    disabled,
  ) {
    return JSON.stringify({
      prefix: prefix,
      country: country || "",
      state: state || "",
      selectedValue: selectedValue || "",
      disabled: !!disabled,
      cities: currentCities || [],
    });
  }

  function ensureBlockCitySelectBound(prefix, customSelect) {
    if (!customSelect || customSelect.dataset.dropiBound) {
      return;
    }

    customSelect.addEventListener("change", function () {
      var input = getBlockField(prefix, "city");

      if (!input) {
        return;
      }

      syncBlockCityInput(input, customSelect.value);
    });

    customSelect.dataset.dropiBound = "1";
  }

  function isDropiBlockNode(node) {
    return !!(
      node &&
      node.nodeType === 1 &&
      (node.classList.contains("dropi-block-city-select") ||
        node.closest(".dropi-block-city-select"))
    );
  }

  function blockCheckoutMutationRequiresRender(mutations) {
    return mutations.some(function (mutation) {
      if (mutation.type !== "childList") {
        return false;
      }

      if (
        mutation.target &&
        mutation.target.nodeType === 1 &&
        isDropiBlockNode(mutation.target)
      ) {
        return false;
      }

      var nodes = Array.prototype.slice
        .call(mutation.addedNodes || [])
        .concat(Array.prototype.slice.call(mutation.removedNodes || []));

      if (!nodes.length) {
        return false;
      }

      return nodes.some(function (node) {
        if (!node || node.nodeType !== 1) {
          return false;
        }

        if (isDropiBlockNode(node)) {
          return false;
        }

        return !!(
          node.matches(
            ".wc-block-components-address-form, .wc-block-components-text-input, .wc-block-components-address-form__city, .wc-block-components-address-form__state, .wc-block-components-state-input",
          ) ||
          node.querySelector(
            "#billing-city, #shipping-city, #billing-state, #shipping-state, #billing-country, #shipping-country",
          )
        );
      });
    });
  }

  function removeBlockCitySelect(prefix) {
    var customWrapper = document.getElementById(prefix + "-city-dropi-wrapper");
    var cityInput = getBlockField(prefix, "city");
    var cityWrapper = cityInput
      ? cityInput.closest(".wc-block-components-address-form__city")
      : null;

    if (customWrapper) {
      customWrapper.remove();
    }

    if (cityWrapper) {
      cityWrapper.style.display = "";
    }
  }

  function syncBlockCityInput(cityInput, nextValue) {
    var currentValue = cityInput.value || "";

    if (currentValue === nextValue) {
      return;
    }

    setNativeValue(cityInput, nextValue);
    dispatchNativeEvents(cityInput);
  }

  function renderBlockCitySelect(prefix) {
    var cityInput = getBlockField(prefix, "city");
    var cityWrapper = cityInput
      ? cityInput.closest(".wc-block-components-address-form__city")
      : null;
    var stateField = getBlockField(prefix, "state");
    var countryField = getBlockField(prefix, "country");

    if (!cityInput || !cityWrapper || !countryField) {
      removeBlockCitySelect(prefix);
      return;
    }

    var label = cityWrapper.querySelector("label");
    var labelText = label ? label.textContent : "City";
    var country = countryField.value;
    var state = stateField ? stateField.value : "";
    var currentCities = getCitiesForLocation(country, state);
    var requiresState = countryRequiresState(country);
    var customWrapper = document.getElementById(prefix + "-city-dropi-wrapper");
    var customSelect;
    var selectedValue = cityInput.value || "";
    var renderSignature;

    if (!(currentCities instanceof Array) || currentCities.length === 0) {
      if (requiresState && !state) {
        if (!customWrapper) {
          customWrapper = createBlockCitySelectWrapper(prefix, labelText);
          cityWrapper.insertAdjacentElement("afterend", customWrapper);
        }

        customSelect = customWrapper.querySelector("select");
        ensureBlockCitySelectBound(prefix, customSelect);
        renderSignature = getBlockRenderSignature(
          prefix,
          country,
          state,
          [],
          "",
          true,
        );

        if (customWrapper.dataset.dropiRenderSignature !== renderSignature) {
          customSelect.innerHTML =
            '<option value="">' + getCityPlaceholder() + "</option>";
          customWrapper.dataset.dropiRenderSignature = renderSignature;
        }

        customSelect.disabled = true;
        customSelect.value = "";
        cityWrapper.style.display = "none";
        syncBlockCityInput(cityInput, "");
        return;
      }

      removeBlockCitySelect(prefix);
      return;
    }

    if (!customWrapper) {
      customWrapper = createBlockCitySelectWrapper(prefix, labelText);
      cityWrapper.insertAdjacentElement("afterend", customWrapper);
    }

    customSelect = customWrapper.querySelector("select");
    ensureBlockCitySelectBound(prefix, customSelect);
    renderSignature = getBlockRenderSignature(
      prefix,
      country,
      state,
      currentCities,
      selectedValue,
      false,
    );

    if (customWrapper.dataset.dropiRenderSignature !== renderSignature) {
      var options = '<option value="">' + getCityPlaceholder() + "</option>";

      currentCities.forEach(function (cityName) {
        var selected = selectedValue === cityName ? ' selected="selected"' : "";
        options +=
          '<option value="' +
          cityName +
          '"' +
          selected +
          ">" +
          cityName +
          "</option>";
      });

      customSelect.innerHTML = options;
      customWrapper.dataset.dropiRenderSignature = renderSignature;
    }

    customSelect.disabled = false;

    if (!currentCities.includes(selectedValue)) {
      if (customSelect.value !== "") {
        customSelect.value = "";
      }
      syncBlockCityInput(cityInput, "");
    } else if (customSelect.value !== selectedValue) {
      customSelect.value = selectedValue;
    }

    cityWrapper.style.display = "none";
  }

  function queueBlockCityRender() {
    if (blockCheckoutRenderQueued) {
      return;
    }

    blockCheckoutRenderQueued = true;
    window.requestAnimationFrame(function () {
      blockCheckoutRenderQueued = false;
      renderBlockCitySelect("billing");
      renderBlockCitySelect("shipping");
    });
  }

  function initBlockCheckoutCitySelectors() {
    blockCheckoutObserverTarget =
      document.querySelector(".wc-block-checkout") ||
      document.querySelector(".wc-block-components-address-form") ||
      document.querySelector("form.wc-block-checkout__form") ||
      document.querySelector("form");

    if (!blockCheckoutObserverTarget) {
      return;
    }

    queueBlockCityRender();

    document.addEventListener(
      "change",
      function (event) {
        var target = event.target;

        if (!target || !target.id) {
          return;
        }

        if (
          target.id === "billing-country" ||
          target.id === "billing-state" ||
          target.id === "shipping-country" ||
          target.id === "shipping-state"
        ) {
          queueBlockCityRender();
        }
      },
      true,
    );

    if (!blockCheckoutObserver) {
      blockCheckoutObserver = new MutationObserver(function (mutations) {
        if (!blockCheckoutMutationRequiresRender(mutations)) {
          return;
        }

        queueBlockCityRender();
      });

      blockCheckoutObserver.observe(blockCheckoutObserverTarget, {
        childList: true,
        subtree: true,
      });
    }
  }

  initClassicCitySelectors();
  initBlockCheckoutCitySelectors();
})(jQuery);
