.PHONY: about help zip clear

zip: clear
	mkdir ./multi-crypto-currency-payment
	cp -R -t ./multi-crypto-currency-payment ./assets ./inc ./vendor LICENSE.txt mccp.php readme.txt
	zip -r ./multi-crypto-currency-payment.zip ./multi-crypto-currency-payment
	rm -rf ./multi-crypto-currency-payment

help: about

about:
	@echo "Makefile to help create .zip file"

clear:
	rm -f ./multi-crypto-currency-payment*

