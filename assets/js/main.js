document.addEventListener("DOMContentLoaded", function () {
  // Handle file input change for preview
  const fileInputs = document.querySelectorAll(".file-input");
  fileInputs.forEach((input) => {
    input.addEventListener("change", function () {
      const previewContainer = document.querySelector(".media-preview");

      // If preview container doesn't exist in current context, return
      if (!previewContainer) return;

      let previewElement;

      // Handle different contexts (posts vs chat)
      if (document.querySelector(".media-preview-element")) {
        previewElement = document.querySelector(".media-preview-element");

        // Clear existing content if it's a container element
        if (
          previewElement.tagName !== "IMG" &&
          previewElement.tagName !== "VIDEO"
        ) {
          while (previewElement.firstChild) {
            previewElement.removeChild(previewElement.firstChild);
          }
        }
      } else {
        previewElement = document.querySelector(
          ".media-preview img, .media-preview video"
        );
      }

      // Disable other file inputs when one is selected
      const photoInput = document.querySelector("input[name='photo']");
      const videoInput = document.querySelector("input[name='video']");

      if (this.name === "photo" && videoInput) {
        videoInput.disabled = true;
      } else if (this.name === "video" && photoInput) {
        photoInput.disabled = true;
      }

      if (this.files && this.files[0]) {
        const file = this.files[0];
        const reader = new FileReader();

        reader.onload = function (e) {
          previewContainer.style.display = "block";

          if (file.type.startsWith("image/")) {
            // Handle image preview
            if (previewElement && previewElement.tagName === "IMG") {
              // Update existing image
              previewElement.src = e.target.result;
            } else if (previewElement && previewElement.tagName === "VIDEO") {
              // Replace video with image
              const img = document.createElement("img");
              img.className = "media-preview-element";
              img.src = e.target.result;
              previewElement.parentNode.replaceChild(img, previewElement);
              previewElement = img;
            } else if (
              previewElement &&
              previewElement.tagName !== "IMG" &&
              previewElement.tagName !== "VIDEO"
            ) {
              // Add to container
              const img = document.createElement("img");
              img.src = e.target.result;
              img.className = "img-fluid rounded";
              previewElement.appendChild(img);
            } else {
              // Create new image if no preview element exists
              const img = document.createElement("img");
              img.className = "media-preview-element";
              img.src = e.target.result;
              previewContainer.appendChild(img);
            }
          } else if (file.type.startsWith("video/")) {
            // Handle video preview
            if (previewElement && previewElement.tagName === "VIDEO") {
              // Update existing video
              previewElement.src = e.target.result;
            } else if (previewElement && previewElement.tagName === "IMG") {
              // Replace image with video
              const video = document.createElement("video");
              video.className = "media-preview-element";
              video.controls = true;
              video.src = e.target.result;
              previewElement.parentNode.replaceChild(video, previewElement);
              previewElement = video;
            } else if (
              previewElement &&
              previewElement.tagName !== "IMG" &&
              previewElement.tagName !== "VIDEO"
            ) {
              // Add to container
              const video = document.createElement("video");
              video.src = e.target.result;
              video.controls = true;
              video.className = "img-fluid rounded";
              previewElement.appendChild(video);
            } else {
              // Create new video if no preview element exists
              const video = document.createElement("video");
              video.className = "media-preview-element";
              video.controls = true;
              video.src = e.target.result;
              previewContainer.appendChild(video);
            }
          }
        };

        reader.readAsDataURL(file);
      }
    });
  });

  // Handle remove media button
  const removeMediaBtn = document.querySelector(".remove-media-btn");
  if (removeMediaBtn) {
    removeMediaBtn.addEventListener("click", function () {
      const previewContainer = document.querySelector(".media-preview");
      const previewElement = document.querySelector(
        ".media-preview-element, .media-preview img, .media-preview video"
      );
      const fileInputs = document.querySelectorAll(".file-input");

      if (previewContainer) {
        previewContainer.style.display = "none";
      }

      if (previewElement) {
        if (
          previewElement.tagName === "IMG" ||
          previewElement.tagName === "VIDEO"
        ) {
          // For direct elements, remove src
          previewElement.src = "";
        } else {
          // For container elements, remove children
          while (previewElement.firstChild) {
            previewElement.removeChild(previewElement.firstChild);
          }
        }
      }

      fileInputs.forEach((input) => {
        input.value = "";
        input.disabled = false; // Re-enable all inputs
      });
    });
  }

  // Handle like and dislike buttons
  const likeButtons = document.querySelectorAll(".like-btn");
  const dislikeButtons = document.querySelectorAll(".dislike-btn");

  likeButtons.forEach((button) => {
    button.addEventListener("click", function () {
      const postId = this.dataset.postId;
      const counterElement = this.querySelector(".like-count");

      fetch("/ssipfix/api/like.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: `post_id=${postId}&action=like`,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            // Update like count
            counterElement.textContent = data.likes;

            // Toggle active state
            this.classList.toggle("active-like", data.userLiked);

            // Remove active state from dislike button
            const dislikeBtn = document.querySelector(
              `.dislike-btn[data-post-id="${postId}"]`
            );
            const dislikeCounter = dislikeBtn.querySelector(".dislike-count");
            dislikeBtn.classList.remove("active-dislike");
            dislikeCounter.textContent = data.dislikes;
          }
        })
        .catch((error) => {
          console.error("Error:", error);
        });
    });
  });

  dislikeButtons.forEach((button) => {
    button.addEventListener("click", function () {
      const postId = this.dataset.postId;
      const counterElement = this.querySelector(".dislike-count");

      fetch("/ssipfix/api/like.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: `post_id=${postId}&action=dislike`,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            // Update dislike count
            counterElement.textContent = data.dislikes;

            // Toggle active state
            this.classList.toggle("active-dislike", data.userDisliked);

            // Remove active state from like button
            const likeBtn = document.querySelector(
              `.like-btn[data-post-id="${postId}"]`
            );
            const likeCounter = likeBtn.querySelector(".like-count");
            likeBtn.classList.remove("active-like");
            likeCounter.textContent = data.likes;
          }
        })
        .catch((error) => {
          console.error("Error:", error);
        });
    });
  });

  // Toggle comment form
  const commentToggleButtons = document.querySelectorAll(".comment-toggle-btn");
  commentToggleButtons.forEach((button) => {
    button.addEventListener("click", function () {
      const postId = this.dataset.postId;
      const commentForm = document.querySelector(
        `.comment-form[data-post-id="${postId}"]`
      );
      const commentSection = document.querySelector(
        `.comment-section[data-post-id="${postId}"]`
      );

      commentForm.style.display =
        commentForm.style.display === "none" ? "block" : "none";
      commentSection.style.display =
        commentSection.style.display === "none" ? "block" : "none";
    });
  });

  // Character counter for post and comment input
  const textareas = document.querySelectorAll(".count-chars");
  textareas.forEach((textarea) => {
    textarea.addEventListener("input", function () {
      const counter = document.querySelector(`#${this.dataset.counter}`);
      const maxLength = this.getAttribute("maxlength");
      const remaining = maxLength - this.value.length;

      counter.textContent = `${remaining} karakter tersisa`;

      if (remaining < 20) {
        counter.classList.add("text-danger");
      } else {
        counter.classList.remove("text-danger");
      }
    });
  });
});
