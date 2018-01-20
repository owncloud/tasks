# Makefile for building the project

app_name=tasks
project_dir=$(CURDIR)/../$(app_name)
build_dir=$(CURDIR)/build
appstore_dir=$(build_dir)/appstore
signed_dir=$(build_dir)/appstore_signed
package_name=$(app_name)

clean:
	rm -rf $(appstore_dir)
	rm -rf $(signed_dir)

sign:
	rm -rf $(signed_dir)
	mkdir -p $(signed_dir)
	rsync -av \
	$(project_dir) $(signed_dir) \
	--exclude=$(app_name)/tests \
	--exclude=$(app_name)/.git \
	--exclude=$(app_name)/.idea \
	--exclude=$(app_name)/.editorconfig \
	--exclude=$(app_name)/.gitattributes \
	--exclude=$(app_name)/.gitignore \
	--exclude=$(app_name)/.scrutinizer.yml \
	--exclude=$(app_name)/.travis.yml \
	--exclude=$(app_name)/build.xml \
	--exclude=$(app_name)/CONTRIBUTING.md \
	--exclude=$(app_name)/tasks.sublime-project \
	--exclude=$(app_name)/tasks.sublime-workspace \
	--exclude=$(app_name)/issue_template.md \
	--exclude=$(app_name)/Makefile \
	--exclude=$(app_name)/phpunit.xml \
	--exclude=$(app_name)/README.md \
	--exclude=$(app_name)/build \
	--exclude=$(app_name)/img/source \
	--exclude=$(app_name)/js/.bowerrc \
	--exclude=$(app_name)/js/README.md \
	--exclude=$(app_name)/js/.jshintrc \
	--exclude=$(app_name)/js/bower.json \
	--exclude=$(app_name)/js/Gruntfile.js \
	--exclude=$(app_name)/js/package.json \
	--exclude=$(app_name)/js/README.mkdir \
	--exclude=$(app_name)/js/app \
	--exclude=$(app_name)/js/config \
	--exclude=$(app_name)/js/node_modules \
	--exclude=$(app_name)/js/vendor/**/.bower.json \
	--exclude=$(app_name)/js/vendor/**/.npmignore \
	--exclude=$(app_name)/js/vendor/**/bower.json \
	--exclude=$(app_name)/js/vendor/**/Gruntfile.js \
	--exclude=$(app_name)/js/vendor/**/package.json \
	--exclude=$(app_name)/js/vendor/**/*.md \
	--exclude=$(app_name)/js/vendor/**/karma.conf.js \
	--exclude=$(app_name)/js/vendor/**/*.map \
	--exclude=$(app_name)/js/vendor/angular-mocks \
	--exclude=$(app_name)/js/vendor/angular/angular.js \
	--exclude=$(app_name)/js/vendor/angular-animate/angular-animate.js \
	--exclude=$(app_name)/js/vendor/angular-draganddrop/angular-drag-and-drop-lists.js \
	--exclude=$(app_name)/js/vendor/angular-route/angular-route.js \
	--exclude=$(app_name)/js/vendor/angular-sanitize/angular-sanitize.js \
	--exclude=$(app_name)/js/vendor/angular-ui-select/dist/select.js \
	--exclude=$(app_name)/js/vendor/angular-ui-select/dist/select.css \
	--exclude=$(app_name)/js/vendor/angular-ui-select/docs \
	--exclude=$(app_name)/js/vendor/angular-ui-select/composer.json \
	--exclude=$(app_name)/js/vendor/angular-ui-select/deploy-docs.sh \
	--exclude=$(app_name)/js/vendor/jstzdetect/jstz.js \
	--exclude=$(app_name)/js/vendor/davclient.js/index.html \
	--exclude=$(app_name)/js/vendor/ical.js/build/benchmark \
	--exclude=$(app_name)/js/vendor/ical.js/lib \
	--exclude=$(app_name)/js/vendor/ical.js/samples \
	--exclude=$(app_name)/js/vendor/ical.js/sandbox \
	--exclude=$(app_name)/js/vendor/ical.js/tasks \
	--exclude=$(app_name)/js/vendor/ical.js/test-agent \
	--exclude=$(app_name)/js/vendor/ical.js/test-agent-server.js \
	--exclude=$(app_name)/js/vendor/ical.js/test-agent-coverage.json \
	--exclude=$(app_name)/js/vendor/jquery-timepicker/i18n \
	--exclude=$(app_name)/js/vendor/jquery-timepicker/include \
	--exclude=$(app_name)/js/vendor/jquery-timepicker/legacy_1.2.6 \
	--exclude=$(app_name)/js/vendor/jquery-timepicker/tests \
	--exclude=$(app_name)/js/vendor/jquery-timepicker/index.html \
	--exclude=$(app_name)/timezones/INFO.md
	chown -R www-data $(signed_dir)
	sudo -u www-data php ../../occ integrity:sign-app --privateKey=$(build_dir)/app-signing/tasks.key --certificate=$(build_dir)/app-signing/tasks.crt --path=$(signed_dir)/$(app_name)

appstore:
	mkdir -p $(appstore_dir)
	cd $(signed_dir); \
	tar cvzf $(appstore_dir)/$(package_name).tar.gz $(app_name)/
