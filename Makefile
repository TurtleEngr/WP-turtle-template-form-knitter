# Makefile for github.com:TurtleEngr/WP-turtle-template-form-knitter

# ----------
# Macros

SHELL := /bin/bash

# Plugin name (slug)
mName = turtle-template-form-knitter

# Repository name
mProj = WP-$(mName)

# Plugin install files
mProduct = dist/$(mName)-VERSION.zip

# Files that go in the plugin's zip file
mBuildList = \
    dist/$(mName) \
    dist/$(mName)/$(mName).php \
    dist/$(mName)/js/ttfk-paste.js \
    dist/$(mName)/readme.txt \
    dist/$(mName)/LICENSE

# Generated files that will be put in docs/
mDocList = \
	docs/development.html \
	docs/example.html \
	docs/index.html

# Get branch name
mBranch = $$(git rev-parse --abbrev-ref HEAD 2>/dev/null)

# Location for saving the built plugin. See the rsync commands.
mServer = moria.whyayh.com
mPubDev = /rel/development/software/own/$(mProj)
mPubRel = /rel/released/software/own/$(mProj)

# --------------------

usage : verify
	@echo
	@echo '===================='
	@echo 'Usage'
	@echo "update - get latest version from github"
	@echo "build - $(mProduct)"
	@echo "incPatch, incMinor, or incMajor - do these before save or publish"
	@echo "    Then update Changelog in readme.txt"
	@echo "save - ci, push develop to github"
	@echo "    copy to $(mPubDev)"
	@echo "publish - tag, ci, push to develop, merge to main, push to main"
	@echo "    copy to $(mPubRel)"
	@echo "release - TBD"
	@echo
	@echo '===================='
	@echo "VERSION: $$(cat VERSION)"
	@echo "VERSION-dev: $$(cat VERSION-dev)"
	@echo "VERSION-pub: $$(cat VERSION-pub)"
	@echo "VERSION-rel: $$(cat VERSION-rel)"
	@echo
	@echo '===================='
	@git st

verify :
	@echo
	@echo '===================='
	@echo 'Verify Requirements'
	which emacs >/dev/null 2>&1
	which git >/dev/null 2>&1
	which make >/dev/null 2>&1
	which pandoc >/dev/null 2>&1
	which perl >/dev/null 2>&1
	which php >/dev/null 2>&1
	which pod2markdown >/dev/null 2>&1
	which pod2pdf >/dev/null 2>&1
	which sed >/dev/null 2>&1
	which tidy >/dev/null 2>&1
	which zip >/dev/null 2>&1
	which ./.git/hooks/pre-commit >/dev/null 2>&1
	grep -q 'ci = commit' ./.git/config ./.git/config
	grep -q 'co = checkout' ./.git/config ./.git/config
	grep -q 'st = status -s' ./.git/config ./.git/config
	grep -q 'pre-commit-enabled = true' ./.git/config
	grep -q 'check-whitespace = true' ./.git/config
	grep -q 'check-for-tabs = true' ./.git/config

update :
	git co $(mBranch)
	git pull origin $(mBranch)

build : dist-clean update doc $(mProduct)
	@echo 'If OK, make save'

doc : $(mDocList)

save development : check-dev
	-git ci -am Updated
	git push origin $(mBranch)
	-ssh $(mServer) mkdir -p $(mPubDev)
	rsync -a $(mDocList) readme.txt dist/$(mName)-$$(cat VERSION).zip $(mServer):$(mPubDev)
	ssh $(mServer) mv -f $(mPubDev)/index.html $(mPubDev)/README.html
	cp VERSION VERSION-dev
	git ci -am Updated
	git push origin $(mBranch)
	@echo 'If OK, make publish'

publish : check-rel
	if [[ "$(mBranch)" != "develop" ]]; then exit 1; fi
	-git ci -am Updated
	git tag -f "ver-$$(cat VERSION)"
	git push --tags origin develop
	git co main
	git pull --tags origin main
	git merge develop
	git push --tags origin main
	git co develop
	-ssh $(mServer) mkdir -p $(mPubRel)
	rsync -a $(mDocList) readme.txt dist/$(mName)-$$(cat VERSION).zip $(mServer):$(mPubRel)
	ssh $(mServer) mv -f $(mPubRel)/index.html $(mPubRel)/README.html
	cp VERSION VERSION-pub
	-git ci -am Updated
	git push origin develop
	@echo 'If OK, make release'
	@echo 'If done, make dist-clean'

release : check-rel
	@echo TBD - update subversion copy at wordpress.org
	#cp VERSION VERSION-rel
	#@echo 'If done, make dist-clean'

clean :
	-find . -type f -name '*~' -exec rm {} \;

dist-clean : clean
	rm -rf dist

# To remove tags: local and remote
# git tag -d v2.1.1
# git push origin --delete v2.1.1

# --------------------
# Work Targets

$(mProduct) : $(mBuildList)
	php -l $(mName).php
	cd dist; zip -r $(mName)-$$(cat ../VERSION).zip $(mName)
	-touch $@

docs/index.html : README.org VERSION
	bin/rm-trailing-sp $?
	sed -i "s/^ver-[0-9.]*/ver-$$(cat VERSION)/" README.org
	bin/org2html.sh -i README.org -o $@ -s 2
	sed -i 's/\&amp;/\&/g' $@

check-dev :
	if diff -q VERSION VERSION-dev >/dev/null 2>&1; then \
		echo "Development versions must be different."; \
		echo "increment and rebuild."; \
		exit 1; \
	fi

check-rel :
	if diff -q VERSION VERSION-rel >/dev/null 2>&1; then \
		echo "Released versions must be different."; \
		echo "increment and rebuild."; \
		exit 1; \
	fi

# --------------------
# Single Targets

VERSION :
	echo '0.0.0' >$@

incPatch : VERSION
	incver.sh -p

incMinor : VERSION
	incver.sh -m

incMajor : VERSION
	incver.sh -M

dist/$(mName) :
	mkdir -p $@

dist/$(mName)/$(mName).php : VERSION $(mName).php
	sed "s/VERSION/$$(cat VERSION)/" <$(mName).php >$@

dist/$(mName)/js/ttfk-paste.js : js/ttfk-paste.js
	-mkdir -p $(@D)
	cp $? $@

dist/$(mName)/readme.txt : VERSION readme.txt
	sed "s/VERSION/$$(cat VERSION)/" <$? >$@

dist/$(mName)/LICENSE : LICENSE
	-cp $? $@

docs/development.html : development.org
	bin/rm-trailing-sp $?
	bin/org2html.sh -i $? -o $@ -s 2
	sed -i 's/\&amp;/\&/g' $@

docs/example.html : example.org
	bin/rm-trailing-sp $?
	bin/org2html.sh -i $? -o $@ -s 3
	sed -i 's/\&amp;/\&/g' $@
