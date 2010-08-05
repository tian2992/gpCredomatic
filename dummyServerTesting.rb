# Credomatic Payment Gateway for WP-e-Commerce - Dummy Payment Testfile
# Copyright, 2010 - Sebastian Oliva
# http://sebastianoliva.com
#
# Made at the request of http://royalestudios.com/
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License version 3
# as published by the Free Software Foundation.
#
# This software is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.

require 'rubygems'
require 'sinatra'


helpers do
  #inspired from http://wbear.wordpress.com/2010/03/20/sinatra-request-headers-helper/
  def request_headers
    env.inject({}){|acc, (k,v)| acc[$1.downcase] = v if k =~ /^http_(.*)/i; acc}
  end
end

get '/' do
  "Hello!"
end

#faking the endpoint
post '/api/transact.php' do
  vals = "\n"

  vals += request_headers.inspect.to_s

  params.each { |key,val|
      vals = vals + "Key: "+key+"\n\tValue:"+val+"\n"
  }

  logFile = File.new("log.txt", "w")
  logFile.write("Posted params: \n" + vals  + "\n")
  logFile.close

  #returning a dummy, but successful response
  "http://localhost?response=1&responsetext=SUCCESS&authcode=123456&transactionid=1251202176&avsresponse=&cvvresponse=&orderid=Test&type=sale&response_code=100&username=449510&time=1278362479&amount=1&hash=7d2efb3d2b25496d3b68adc58a15c404"

end
