.PHONY: about help zip clear

##
# help
# Displays a (hopefully) useful help screen to the user
#
# NOTE: Keep 'help' as first target in case .DEFAULT_GOAL is not honored
#
help: about ## This help screen

about:
	@echo
	@echo "Makefile to help create .zip file"

clear:
	rm -f ./multi-crypto-currency-payment.zip

zip: clear
	mkdir ./multi-crypto-currency-payment
	cp -R -t ./multi-crypto-currency-payment ./assets ./inc LICENSE.txt mccp.php readme.txt
	zip -r ./multi-crypto-currency-payment.zip ./multi-crypto-currency-payment
	rm -rf ./multi-crypto-currency-payment
