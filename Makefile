# Local mirror of the release workflow's own checks: translations and PHP syntax.
# PHP is not on the dev box, so syntax is checked in a throwaway php:8.1-cli container.
.PHONY: check
check:
	python3 bin/make-pot --check
	docker run --rm -v "$(CURDIR)":/app -w /app php:8.1-cli sh -c 'for f in $$(find . -name "*.php" -not -path "./.git/*"); do php -l "$$f" >/dev/null || exit 1; done; echo "php -l: ok"'
