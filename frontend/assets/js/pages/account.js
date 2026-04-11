    $(function() {
      $("#serverError, #serverSuccess").each(function() {
        const element = $(this);
        setTimeout(function() {
          element.fadeOut(400);
        }, 3500);
      });

      $(document).on("click keydown", ".account-toggle-password", function(event) {
        if (event.type === "keydown" && event.key !== "Enter" && event.key !== " ") {
          return;
        }

        event.preventDefault();

        const icon = $(this);
        const target = (icon.attr("data-target") || "").toString().trim();
        if (!target) {
          return;
        }

        const input = $(target).first();
        if (!input.length) {
          return;
        }

        const reveal = input.attr("type") === "password";
        input.attr("type", reveal ? "text" : "password");
        icon.toggleClass("bi-eye", !reveal).toggleClass("bi-eye-slash", reveal);
      });

      const addressInput = $("#address");
      const mapFrame = $("#address-map-frame");
      let mapRefreshTimer = null;
      let lastMapQuery = "";

      const refreshAddressMap = function(forceUpdate = false) {
        if (!addressInput.length || !mapFrame.length) {
          return;
        }

        const rawAddress = (addressInput.val() || "").toString().trim();
        const query = rawAddress !== "" ? rawAddress : "Pakistan";
        if (!forceUpdate && query === lastMapQuery) {
          return;
        }

        lastMapQuery = query;
        const mapUrl =
          "https://www.google.com/maps?q=" +
          encodeURIComponent(query) +
          "&output=embed";
        mapFrame.attr("src", mapUrl);
      };

      const scheduleAddressMapRefresh = function() {
        if (!addressInput.length || !mapFrame.length) {
          return;
        }

        if (mapRefreshTimer !== null) {
          window.clearTimeout(mapRefreshTimer);
        }

        mapRefreshTimer = window.setTimeout(function() {
          mapRefreshTimer = null;
          refreshAddressMap(false);
        }, 650);
      };

      addressInput.on("input", scheduleAddressMapRefresh);
      addressInput.on("blur change", function() {
        if (mapRefreshTimer !== null) {
          window.clearTimeout(mapRefreshTimer);
          mapRefreshTimer = null;
        }
        refreshAddressMap(true);
      });
      refreshAddressMap(true);

      const profileForm = $("#updateProfileForm");
      const usernameInput = $("#username");
      const emailInput = $("#email");
      const phoneInput = $("#phone");
      const usernameFeedback = $("#usernameLiveFeedback");
      const emailFeedback = $("#emailLiveFeedback");
      const phoneFeedback = $("#phoneLiveFeedback");
      const visibilityInput = $("#profile-visibility");
      const visibilityLabel = $("#profileVisibilityLabel");
      const visibilityIcon = $("#profileVisibilityIcon");
      const visibilityOptions = $(".profile-visibility-option");
      const profileCsrf = profileForm.find('input[name="csrf_token"]').val() || "";

      let usernameTaken = false;
      let usernameLocked = false;
      let usernameLockFromServer = false;
      let emailTaken = false;
      let phoneTaken = false;
      let usernameTimer = null;
      let emailTimer = null;
      let phoneTimer = null;

      const normalizeUsername = function(value) {
        return (value || "")
          .toString()
          .toLowerCase()
          .replace(/\s+/g, "_")
          .replace(/[^a-z0-9_]/g, "")
          .replace(/_+/g, "_")
          .replace(/^_+|_+$/g, "");
      };

      const normalizeEmail = function(value) {
        return (value || "").toString().trim().toLowerCase();
      };

      const normalizePhone = function(value) {
        return (value || "")
          .toString()
          .replace(/\D+/g, "")
          .slice(0, 15);
      };

      const isValidEmail = function(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) && value.length <= 150;
      };

      const isValidPhone = function(value) {
        return /^\d{11,15}$/.test(value);
      };

      const lockActiveFlag = (usernameInput.attr("data-username-lock-active") || "") === "1";
      const usernameLockMessage = (usernameInput.attr("data-username-lock-message") || "").toString().trim();
      const currentUsername = normalizeUsername(
        (usernameInput.attr("data-current-username") || usernameInput.val() || "").toString()
      );

      const setLiveFeedback = function(target, message, color) {
        if (!target.length) {
          return;
        }

        target.text(message).css("color", color || "#9ca3af");
      };

      const setFieldState = function(input, feedback, message, color, borderColor) {
        if (input.length) {
          input.css("border-color", borderColor || "");
        }

        setLiveFeedback(feedback, message, color);
      };

      const updateUsernameLockState = function(value) {
        const normalized = normalizeUsername(value);
        const isChangeAttempt = normalized !== "" && normalized !== currentUsername;
        usernameLocked = (lockActiveFlag || usernameLockFromServer) && isChangeAttempt;

        if (usernameLocked) {
          setFieldState(
            usernameInput,
            usernameFeedback,
            usernameLockMessage || "Username can only be changed once every 90 days.",
            "#ef4444",
            "#ef4444"
          );
          return true;
        }

        return false;
      };

      const runExistsCheck = function(field, value, onDone, onFail) {
        $.post("backend/api/check_exists.php", {
            csrf_token: profileCsrf,
            field,
            value,
            exclude_current: 1,
          })
          .done(function(res) {
            onDone(res || {});
          })
          .fail(function() {
            if (typeof onFail === "function") {
              onFail();
            }
          });
      };

      const applyVisibilityOption = function(rawValue) {
        const value = (rawValue || "").toString().toLowerCase() === "public" ? "public" : "private";
        const option = visibilityOptions.filter(`[data-value="${value}"]`).first();
        const label = (option.attr("data-label") || (value === "public" ? "Public Profile" : "Private Profile")).toString();
        const iconClass = (option.attr("data-icon") || (value === "public" ? "bi-globe2" : "bi-shield-lock")).toString();

        visibilityInput.val(value);
        visibilityLabel.text(label);
        visibilityIcon.attr("class", `bi ${iconClass}`);
        visibilityOptions.removeClass("active");
        option.addClass("active");
      };

      if (visibilityOptions.length && visibilityInput.length) {
        visibilityOptions.on("click", function() {
          applyVisibilityOption($(this).attr("data-value") || "private");
        });

        applyVisibilityOption(visibilityInput.val() || "private");
      }

      if (usernameInput.length && profileForm.length && profileCsrf !== "") {
        usernameInput.on("input", function() {
          const normalized = normalizeUsername(usernameInput.val());
          usernameInput.val(normalized);
          usernameTaken = false;
          usernameLocked = false;
          usernameLockFromServer = false;
          clearTimeout(usernameTimer);
          setFieldState(usernameInput, usernameFeedback, "", "#9ca3af", "");

          if (updateUsernameLockState(normalized)) {
            return;
          }

          if (normalized.length < 3) {
            setLiveFeedback(usernameFeedback, "Use at least 3 characters.", "#9ca3af");
            return;
          }

          usernameTimer = setTimeout(() => {
            runExistsCheck(
              "username",
              normalized,
              function(res) {
                const blocked = !!res?.blocked;
                usernameLockFromServer = !!res?.lock_active;
                usernameLocked = usernameLockFromServer;
                if (usernameLocked) {
                  usernameTaken = true;
                  const lockMessage =
                    typeof res?.message === "string" && res.message.trim() !== "" ?
                    res.message :
                    (usernameLockMessage || "Username can only be changed once every 90 days.");
                  setFieldState(usernameInput, usernameFeedback, lockMessage, "#ef4444", "#ef4444");
                  return;
                }

                usernameTaken = !!res?.exists;
                if (usernameTaken) {
                  const blockedMessage =
                    typeof res?.message === "string" && res.message.trim() !== "" ?
                    res.message :
                    blocked ?
                    "This username is not allowed." :
                    "Username already taken.";
                  setFieldState(usernameInput, usernameFeedback, blockedMessage, "#ef4444", "#ef4444");
                } else {
                  setFieldState(usernameInput, usernameFeedback, "Username available.", "#22c55e", "#22c55e");
                }
              },
              function() {
                usernameTaken = false;
                usernameLocked = false;
                usernameLockFromServer = false;
                setFieldState(usernameInput, usernameFeedback, "", "#9ca3af", "");
              }
            );
          }, 420);
        });
      }

      if (emailInput.length && profileForm.length && profileCsrf !== "") {
        emailInput.on("input", function() {
          const normalized = normalizeEmail(emailInput.val());
          emailInput.val(normalized);
          emailTaken = false;
          clearTimeout(emailTimer);
          setFieldState(emailInput, emailFeedback, "", "#9ca3af", "");

          if (normalized === "") {
            setLiveFeedback(emailFeedback, "Email is required.", "#9ca3af");
            return;
          }

          if (!isValidEmail(normalized)) {
            setFieldState(emailInput, emailFeedback, "Enter a valid email address.", "#9ca3af", "");
            return;
          }

          emailTimer = setTimeout(() => {
            runExistsCheck(
              "email",
              normalized,
              function(res) {
                const blocked = !!res?.blocked;
                emailTaken = !!res?.exists;
                if (emailTaken) {
                  const emailMessage =
                    typeof res?.message === "string" && res.message.trim() !== "" ?
                    res.message :
                    blocked ?
                    "This email is blocked by admin." :
                    "Email already in use.";
                  setFieldState(emailInput, emailFeedback, emailMessage, "#ef4444", "#ef4444");
                } else {
                  setFieldState(emailInput, emailFeedback, "Email available.", "#22c55e", "#22c55e");
                }
              },
              function() {
                emailTaken = false;
                setFieldState(emailInput, emailFeedback, "", "#9ca3af", "");
              }
            );
          }, 420);
        });
      }

      if (phoneInput.length && profileForm.length && profileCsrf !== "") {
        phoneInput.on("input", function() {
          const normalized = normalizePhone(phoneInput.val());
          phoneInput.val(normalized);
          phoneTaken = false;
          clearTimeout(phoneTimer);
          setFieldState(phoneInput, phoneFeedback, "", "#9ca3af", "");

          if (normalized === "") {
            setLiveFeedback(phoneFeedback, "Phone number is required.", "#9ca3af");
            return;
          }

          if (!isValidPhone(normalized)) {
            setFieldState(phoneInput, phoneFeedback, "Use 11 to 15 digits.", "#9ca3af", "");
            return;
          }

          phoneTimer = setTimeout(() => {
            runExistsCheck(
              "phone",
              normalized,
              function(res) {
                const blocked = !!res?.blocked;
                phoneTaken = !!res?.exists;
                if (phoneTaken) {
                  const phoneMessage =
                    typeof res?.message === "string" && res.message.trim() !== "" ?
                    res.message :
                    blocked ?
                    "This phone number is blocked by admin." :
                    "Phone already in use.";
                  setFieldState(phoneInput, phoneFeedback, phoneMessage, "#ef4444", "#ef4444");
                } else {
                  setFieldState(phoneInput, phoneFeedback, "Phone available.", "#22c55e", "#22c55e");
                }
              },
              function() {
                phoneTaken = false;
                setFieldState(phoneInput, phoneFeedback, "", "#9ca3af", "");
              }
            );
          }, 420);
        });
      }

      if (profileForm.length) {
        profileForm.on("submit", function(event) {
          const normalized = normalizeUsername(usernameInput.val());
          const normalizedEmail = normalizeEmail(emailInput.val());
          const normalizedPhone = normalizePhone(phoneInput.val());

          usernameInput.val(normalized);
          emailInput.val(normalizedEmail);
          phoneInput.val(normalizedPhone);

          const lockTriggered = updateUsernameLockState(normalized);
          if (lockTriggered || usernameLocked) {
            event.preventDefault();
            event.stopImmediatePropagation();
            usernameInput.focus();
            return false;
          }

          if (usernameTaken) {
            event.preventDefault();
            event.stopImmediatePropagation();
            usernameInput.focus();
            setFieldState(usernameInput, usernameFeedback, "Choose another username before saving.", "#ef4444", "#ef4444");
            return false;
          }

          if (normalized.length < 3) {
            event.preventDefault();
            event.stopImmediatePropagation();
            usernameInput.focus();
            setFieldState(usernameInput, usernameFeedback, "Username must be 3-24 characters.", "#ef4444", "#ef4444");
            return false;
          }

          if (!isValidEmail(normalizedEmail)) {
            event.preventDefault();
            event.stopImmediatePropagation();
            emailInput.focus();
            setFieldState(emailInput, emailFeedback, "Enter a valid email address.", "#ef4444", "#ef4444");
            return false;
          }

          if (emailTaken) {
            event.preventDefault();
            event.stopImmediatePropagation();
            emailInput.focus();
            setFieldState(emailInput, emailFeedback, "Choose another email before saving.", "#ef4444", "#ef4444");
            return false;
          }

          if (!isValidPhone(normalizedPhone)) {
            event.preventDefault();
            event.stopImmediatePropagation();
            phoneInput.focus();
            setFieldState(phoneInput, phoneFeedback, "Use 11 to 15 digits.", "#ef4444", "#ef4444");
            return false;
          }

          if (phoneTaken) {
            event.preventDefault();
            event.stopImmediatePropagation();
            phoneInput.focus();
            setFieldState(phoneInput, phoneFeedback, "Choose another phone before saving.", "#ef4444", "#ef4444");
            return false;
          }

          return true;
        });
      }

      const updateUploadShell = function(shell, stageText, percent, tone) {
        if (!shell || !shell.length) {
          return;
        }

        const safePercent = Math.max(0, Math.min(100, Math.round(percent || 0)));
        const stage = shell.find("[data-upload-stage]").first();
        const bar = shell.find("[data-upload-bar]").first();
        const percentChip = shell.find("[data-upload-percent]").first();

        shell.removeClass("upload-state-upload upload-state-processing upload-state-success upload-state-error");
        shell.addClass("is-active");

        if (tone === "bg-success") {
          shell.addClass("upload-state-success");
        } else if (tone === "bg-danger") {
          shell.addClass("upload-state-error");
        } else if (safePercent >= 95) {
          shell.addClass("upload-state-processing");
        } else {
          shell.addClass("upload-state-upload");
        }

        shell.removeClass("d-none");
        if (stage.length) {
          stage.text((stageText || "Processing upload...").toString());
        }

        if (percentChip.length) {
          percentChip.text(`${safePercent}%`);
        }

        if (bar.length) {
          bar.removeClass("bg-warning bg-danger bg-success");
          if (tone) {
            bar.addClass(tone);
          }
          bar.css("width", `${safePercent}%`);
        }
      };

      const animateUploadShell = function(shell, stageText, targetPercent, tone, options = {}) {
        if (!shell || !shell.length) {
          return;
        }

        const safeTarget = Math.max(0, Math.min(100, Math.round(targetPercent || 0)));
        const duration = Math.max(120, parseInt(options?.duration, 10) || 360);
        const current = parseInt(shell.attr("data-progress-current") || "0", 10) || 0;

        if (safeTarget <= current) {
          updateUploadShell(shell, stageText, safeTarget, tone);
          shell.attr("data-progress-current", String(safeTarget));
          return;
        }

        const startedAt = Date.now();

        const tick = function() {
          const elapsed = Date.now() - startedAt;
          const progressRatio = Math.max(0, Math.min(1, elapsed / duration));
          const eased = 1 - Math.pow(1 - progressRatio, 3);
          const next = Math.max(current, Math.round(current + (safeTarget - current) * eased));

          updateUploadShell(shell, stageText, next, tone);
          shell.attr("data-progress-current", String(next));

          if (next < safeTarget && progressRatio < 1) {
            window.requestAnimationFrame(tick);
          }
        };

        window.requestAnimationFrame(tick);
      };

      const showServerBanner = function(type, message) {
        const safeType = type === "error" ? "error" : "success";
        const safeMessage = (message || "").toString().trim();
        if (!safeMessage) {
          return;
        }

        const targetId = safeType === "error" ? "serverError" : "serverSuccess";
        const fallbackId = safeType === "error" ? "serverSuccess" : "serverError";

        $(`#${fallbackId}`).remove();

        let banner = $(`#${targetId}`);
        if (!banner.length) {
          banner = $("<div>")
            .attr("id", targetId)
            .appendTo("body");
        }

        banner.stop(true, true).text(safeMessage).show();

        window.setTimeout(() => {
          banner.fadeOut(400);
        }, 3500);
      };

      const applyProfilePictureResponse = function(payload) {
        const picturePath = (payload?.profile_picture || "").toString().trim();
        if (picturePath !== "") {
          const cacheBustedPath = `${picturePath}${picturePath.includes("?") ? "&" : "?"}v=${Date.now()}`;
          $("#accountProfileImage").attr("src", cacheBustedPath);
        }

        const fullName = (payload?.full_name || "").toString().trim();
        if (fullName !== "") {
          $("#accountProfileName").text(fullName);
        }

        const username = (payload?.username || "").toString().trim();
        if (username !== "") {
          $("#accountProfileUsername").text(`@${username}`);
        }

        const email = (payload?.email || "").toString().trim();
        if (email !== "") {
          $("#accountProfileEmail").text(email);
        }

        const visibilityLabel = (payload?.profile_visibility_label || "").toString().trim();
        const visibilityIcon = (payload?.profile_visibility_icon || "bi-shield-lock").toString().trim();
        const visibilityPill = $("#accountProfileVisibility");
        if (visibilityPill.length && visibilityLabel !== "") {
          visibilityPill.empty();
          $("<i>")
            .addClass(`bi ${visibilityIcon || "bi-shield-lock"}`)
            .appendTo(visibilityPill);
          visibilityPill.append(document.createTextNode(` ${visibilityLabel}`));
        }
      };

      const bindUploadProgressForm = function(form, fileSelector, resolveShell, options = {}) {
        if (!form || !form.length) {
          return;
        }

        const expectJsonResponse = !!options?.expectJsonResponse;
        const onJsonSuccess =
          typeof options?.onJsonSuccess === "function" ?
          options.onJsonSuccess :
          null;

        form.off("submit.uploadProgress").on("submit.uploadProgress", function(event) {
          const activeForm = $(this);
          const fileInput = activeForm.find(fileSelector).first();
          if (!fileInput.length) {
            return true;
          }

          const file = fileInput[0]?.files?.[0] || null;
          if (!file) {
            const shell = resolveShell(activeForm);
            if (shell && shell.length) {
              shell.addClass("d-none");
            }
            return true;
          }

          event.preventDefault();
          event.stopImmediatePropagation();

          const shell = resolveShell(activeForm);
          if (shell && shell.length) {
            shell.attr("data-progress-current", "0");
          }
          animateUploadShell(shell, "Uploading file...", 4, "bg-warning", {
            duration: 180,
          });

          const submitBtn = activeForm.find("button[type='submit']").first();
          const originalText = submitBtn.text();
          const loadingText = (submitBtn.data("loading-text") || "Uploading...").toString();
          submitBtn.prop("disabled", true).text(loadingText);

          const restoreButton = function() {
            submitBtn.prop("disabled", false).text(originalText);
          };

          const xhr = new XMLHttpRequest();
          xhr.open(
            (activeForm.attr("method") || "POST").toString().toUpperCase(),
            (activeForm.attr("action") || window.location.href).toString(),
            true
          );
          xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");

          xhr.upload.addEventListener("progress", function(progressEvent) {
            if (!progressEvent.lengthComputable) {
              animateUploadShell(shell, "Uploading file...", 60, "bg-warning", {
                duration: 260,
              });
              return;
            }

            const pct = (progressEvent.loaded / progressEvent.total) * 78;
            const rounded = Math.max(4, Math.round(pct));
            animateUploadShell(shell, `Uploading file... ${rounded}%`, rounded, "bg-warning", {
              duration: 180,
            });
          });

          xhr.upload.addEventListener("load", function() {
            animateUploadShell(
              shell,
              "Upload complete. Parsing and compressing image...",
              90,
              "bg-warning", {
                duration: 420,
              }
            );
          });

          xhr.onerror = function() {
            animateUploadShell(
              shell,
              "Upload failed due to a network error.",
              100,
              "bg-danger", {
                duration: 220,
              }
            );
            restoreButton();
          };

          xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 400) {
              if (expectJsonResponse) {
                let payload = null;
                try {
                  payload = JSON.parse(xhr.responseText || "{}");
                } catch (_error) {
                  payload = null;
                }

                if (!payload || payload.ok !== true) {
                  const jsonError =
                    payload?.message ||
                    (Array.isArray(payload?.errors) && payload.errors.length ?
                      payload.errors[0] :
                      "Upload failed. Please try again.");
                  animateUploadShell(shell, jsonError, 100, "bg-danger", {
                    duration: 240,
                  });
                  showServerBanner("error", jsonError);
                  restoreButton();
                  return;
                }

                const nextToken = (payload?.csrf_token || "").toString().trim();
                if (nextToken !== "") {
                  $("input[name='csrf_token']").val(nextToken);
                }

                if (onJsonSuccess) {
                  onJsonSuccess(payload, activeForm);
                }

                const successMessage =
                  (payload?.message || "Profile picture updated successfully.")
                  .toString()
                  .trim();
                animateUploadShell(shell, "Finalizing profile update...", 98, "bg-warning", {
                  duration: 240,
                });
                animateUploadShell(shell, successMessage, 100, "bg-success", {
                  duration: 280,
                });
                showServerBanner("success", successMessage);
                restoreButton();

                window.setTimeout(() => {
                  shell.removeClass("is-active");
                  shell.addClass("d-none");
                }, 1400);
                return;
              }

              animateUploadShell(shell, "Upload completed. Refreshing...", 100, "bg-success", {
                duration: 220,
              });
              document.open();
              document.write(xhr.responseText || "");
              document.close();
              return;
            }

            animateUploadShell(
              shell,
              "Upload failed. Please try again.",
              100,
              "bg-danger", {
                duration: 240,
              }
            );
            restoreButton();
          };

          const formData = new FormData(activeForm[0]);
          if (expectJsonResponse) {
            formData.set("ajax", "1");
          }
          xhr.send(formData);
          return false;
        });
      };

      bindUploadProgressForm(
        $("#updateProfilePictureForm"),
        "#profilePictureInput",
        function() {
          return $("#profileUploadProgress");
        }, {
          expectJsonResponse: true,
          onJsonSuccess: applyProfilePictureResponse,
        }
      );

      $(".refund-request-form").each(function() {
        const form = $(this);
        bindUploadProgressForm(form, ".refund-evidence-input", function(activeForm) {
          return activeForm.find("[data-upload-progress-shell]").first();
        });
      });

      $("form").on("submit", function() {
        const btn = $(this).find("button[type='submit']").first();
        if (!btn.length || btn.prop("disabled")) {
          return;
        }
        const loadingText = btn.data("loading-text");
        btn.prop("disabled", true);
        if (loadingText) {
          btn.text(loadingText);
        }
      });
    });
