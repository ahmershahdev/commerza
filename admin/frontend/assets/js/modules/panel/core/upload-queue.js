function ensureUploadQueueUi(fileInput) {
  if (!fileInput) {
    return null;
  }

  const key = (fileInput.id || fileInput.name || "media-upload").toString();
  let shell = document.querySelector(`[data-upload-status-for="${key}"]`);
  if (!shell) {
    shell = document.createElement("div");
    shell.className = "upload-queue-status d-none";
    shell.setAttribute("data-upload-status-for", key);
    shell.innerHTML = `
      <div class="upload-queue-meta">
        <strong data-upload-heading>Upload Queue</strong>
        <span data-upload-summary>Waiting...</span>
      </div>
      <div class="progress" style="height:6px;">
        <div class="progress-bar bg-warning" role="progressbar" data-upload-progress style="width:0%">0%</div>
      </div>
      <ul class="upload-queue-list" data-upload-list></ul>
    `;

    const anchor = fileInput.closest(".d-flex") || fileInput.parentElement;
    if (anchor?.parentNode) {
      anchor.parentNode.insertBefore(shell, anchor.nextSibling);
    } else {
      fileInput.insertAdjacentElement("afterend", shell);
    }
  }

  return shell;
}

function initUploadQueueUi(shell, files) {
  if (!shell) {
    return;
  }

  const list = shell.querySelector("[data-upload-list]");
  const heading = shell.querySelector("[data-upload-heading]");
  const summary = shell.querySelector("[data-upload-summary]");
  const progress = shell.querySelector("[data-upload-progress]");

  if (heading) {
    heading.textContent = `Upload Queue (${files.length})`;
  }
  if (summary) {
    summary.textContent = "0 completed";
  }
  if (progress) {
    progress.style.width = "0%";
    progress.textContent = "0%";
  }

  if (list) {
    list.innerHTML = files
      .map(
        (file, index) => `
          <li class="upload-queue-item" data-upload-item="${index}">
            <div>
              <div class="upload-file-line">${uploadFileLabel(file?.name)}</div>
              <div class="upload-note-line" data-upload-note>Queued...</div>
              <div class="upload-path-line d-none" data-upload-path></div>
            </div>
            <span class="upload-stage-badge" data-upload-stage>queued</span>
          </li>
        `,
      )
      .join("");
  }

  shell.classList.remove("d-none");
}

function updateUploadQueueTotals(shell, completed, total, success, failed) {
  if (!shell) {
    return;
  }

  const summary = shell.querySelector("[data-upload-summary]");
  const progress = shell.querySelector("[data-upload-progress]");
  const pct =
    total > 0 ? Math.min(100, Math.round((completed / total) * 100)) : 0;

  if (summary) {
    summary.textContent = `${completed}/${total} done | ${success} success | ${failed} failed`;
  }
  if (progress) {
    progress.style.width = `${pct}%`;
    progress.textContent = `${pct}%`;
    progress.classList.toggle("bg-danger", failed > 0 && completed === total);
    progress.classList.toggle(
      "bg-success",
      failed === 0 && completed === total,
    );
    progress.classList.toggle("bg-warning", completed < total);
  }
}

function updateUploadQueueItem(
  shell,
  index,
  stage,
  note,
  path = "",
  isError = false,
) {
  if (!shell) {
    return;
  }

  const row = shell.querySelector(`[data-upload-item="${index}"]`);
  if (!row) {
    return;
  }

  const noteEl = row.querySelector("[data-upload-note]");
  const stageEl = row.querySelector("[data-upload-stage]");
  const pathEl = row.querySelector("[data-upload-path]");

  if (noteEl) {
    noteEl.textContent = (note || "").toString();
  }
  if (stageEl) {
    stageEl.textContent = (stage || "queued").toString();
  }

  if (pathEl) {
    const safePath = (path || "").toString().trim();
    if (safePath) {
      pathEl.textContent = safePath;
      pathEl.classList.remove("d-none");
    } else {
      pathEl.textContent = "";
      pathEl.classList.add("d-none");
    }
  }

  row.classList.toggle("is-error", Boolean(isError));
  row.classList.toggle("is-done", !isError && stage === "done");
}

function uploadAdminMedia(target, file, callbacks = {}) {
  if (!target || !file) {
    return Promise.reject(new Error("Select a valid file before uploading."));
  }

  const onUploadProgress =
    typeof callbacks.onUploadProgress === "function"
      ? callbacks.onUploadProgress
      : () => {};
  const onStage =
    typeof callbacks.onStage === "function" ? callbacks.onStage : () => {};

  const formData = new FormData();
  formData.append("target", target);
  formData.append("file", file);

  const scope = (target || "upload").toString().trim().toLowerCase();
  const requestId = `admin-${scope}-${Date.now().toString(36)}-${Math.random().toString(16).slice(2, 14)}`;

  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open("POST", ADMIN_MEDIA_API, true);
    xhr.withCredentials = true;
    xhr.setRequestHeader("X-CSRF-Token", ADMIN_CSRF_TOKEN);
    xhr.setRequestHeader("X-Request-ID", requestId);

    let stageSent = false;
    const moveToServerStage = () => {
      if (stageSent) {
        return;
      }
      stageSent = true;
      onStage("parsing");
    };

    xhr.upload.addEventListener("progress", (event) => {
      if (!event.lengthComputable) {
        onUploadProgress(0);
        return;
      }
      const pct = Math.min(100, Math.round((event.loaded / event.total) * 100));
      onUploadProgress(pct);
      if (pct >= 100) {
        moveToServerStage();
      }
    });

    xhr.upload.addEventListener("load", () => {
      onUploadProgress(100);
      moveToServerStage();
    });

    xhr.onerror = () => {
      const offline =
        typeof navigator !== "undefined" && navigator.onLine === false;
      reject(
        new Error(
          offline
            ? "Network is offline. Reconnect internet and retry upload."
            : "Network error while uploading file.",
        ),
      );
    };

    xhr.onload = () => {
      let result = null;
      try {
        result = JSON.parse(xhr.responseText || "{}");
      } catch (error) {
        result = null;
      }

      if (xhr.status < 200 || xhr.status >= 300 || !result?.ok) {
        reject(new Error(result?.message || "Upload failed."));
        return;
      }

      const path = (result?.payload?.path || "").toString();
      if (!path) {
        reject(new Error("Uploaded file path is missing."));
        return;
      }

      resolve({
        ...result,
        payload: {
          ...(result?.payload || {}),
          path,
        },
      });
    };

    onStage("uploading");
    xhr.send(formData);
  });
}

async function runConcurrentUploads(files, concurrency, worker) {
  const safeConcurrency = Math.max(1, Math.min(concurrency, files.length || 1));
  const results = new Array(files.length);
  let pointer = 0;

  async function runner() {
    while (pointer < files.length) {
      const current = pointer;
      pointer += 1;

      try {
        results[current] = await worker(files[current], current);
      } catch (error) {
        results[current] = { ok: false, error };
      }
    }
  }

  const workers = Array.from({ length: safeConcurrency }, () => runner());
  await Promise.all(workers);

  return results;
}

function bindUploadControl(buttonSelector, inputSelector, target, onComplete) {
  $(buttonSelector)
    .off("click")
    .on("click", async function () {
      const fileInput = document.querySelector(inputSelector);
      const files = Array.from(fileInput?.files || []).filter(
        (file) => file && typeof file.name === "string",
      );

      if (!files.length) {
        showNotification("Please choose one or more files first.", "warning");
        return;
      }

      const btn = $(this);
      const originalHtml = btn.html();
      btn
        .prop("disabled", true)
        .html('<span class="spinner-border spinner-border-sm"></span>');

      const shell = ensureUploadQueueUi(fileInput);
      initUploadQueueUi(shell, files);

      const parallelism = 1;
      let completed = 0;
      let success = 0;
      let failed = 0;
      const uploadedPaths = [];

      updateUploadQueueTotals(shell, completed, files.length, success, failed);

      try {
        await runConcurrentUploads(files, parallelism, async (file, index) => {
          updateUploadQueueItem(
            shell,
            index,
            "uploading",
            `Uploading ${formatUploadSize(file.size)}...`,
          );

          try {
            const result = await uploadAdminMedia(target, file, {
              onUploadProgress: (pct) => {
                if (pct >= 100) {
                  return;
                }
                updateUploadQueueItem(
                  shell,
                  index,
                  "uploading",
                  `Uploading... ${pct}%`,
                );
              },
              onStage: (stage) => {
                if (stage === "parsing") {
                  updateUploadQueueItem(
                    shell,
                    index,
                    "parsing",
                    "Server is parsing/compressing...",
                  );
                }
              },
            });

            const item = Array.isArray(result?.payload?.items)
              ? result.payload.items.find((entry) => entry?.status === "ok")
              : null;
            const outputPath = (result?.payload?.path || "").toString();
            const outputSize = Number.parseFloat(item?.size_kb || 0) || 0;
            const inputSize =
              Number.parseFloat(item?.original_size_kb || 0) || 0;
            const parser = (item?.parser || "validated").toString();

            const note =
              inputSize > 0 && outputSize > 0
                ? `${parser} | ${inputSize.toFixed(1)} KB -> ${outputSize.toFixed(1)} KB`
                : `${parser} | ${formatUploadSize(file.size)}`;

            updateUploadQueueItem(shell, index, "done", note, outputPath);

            success += 1;
            if (outputPath) {
              uploadedPaths.push(outputPath);
            }

            return { ok: true, result };
          } catch (error) {
            failed += 1;
            updateUploadQueueItem(
              shell,
              index,
              "failed",
              error?.message || "Upload failed.",
              "",
              true,
            );
            return { ok: false, error };
          } finally {
            completed += 1;
            updateUploadQueueTotals(
              shell,
              completed,
              files.length,
              success,
              failed,
            );
          }
        });

        if (uploadedPaths.length && typeof onComplete === "function") {
          onComplete(uploadedPaths[0], uploadedPaths);
        }

        if (fileInput) {
          fileInput.value = "";
        }

        if (success > 0) {
          const msg =
            files.length > 1
              ? `Processed ${success}/${files.length} file(s).`
              : "File uploaded.";
          showNotification(msg, failed > 0 ? "warning" : "success");
        } else {
          showNotification("Unable to upload selected files.", "danger");
        }
      } catch (error) {
        showNotification(error?.message || "Unable to upload file.", "danger");
      } finally {
        btn.prop("disabled", false).html(originalHtml);
      }
    });
}

