# Service Screener

This is an unofficial guidance tool for the AWS environment.

## Overview
Service Screener is a tool that allows AWS customers to automate checks on their environment and services based on the [AWS Well Architected Framework](https://aws.amazon.com/architecture/well-architected/). The purpose of this tool is to provide recommendations on how to improve upon existing setup and configuration. This is not intended to be a replacement of the [AWS Well Architected Tool](https://aws.amazon.com/well-architected-tool/) but rather a complement to it. 

## Prerequisites
1. Please review the [DISCLAIMER](./DISCLAIMER.md) before proceeding. 
2. You must have an existing AWS Account.
3. You must have an IAM user with sufficient read permissions for all of the services to be reviewed. See example [here](https://docs.aws.amazon.com/IAM/latest/UserGuide/reference_policies_examples_iam_read-only-console.html). The user must also have full access to AWS CloudShell i.e. AWSCloudShellFullAccess. 
4. [Login to your AWS account](https://docs.aws.amazon.com/cloudshell/latest/userguide/getting-started.html#start-session) using the above IAM user. 
5. Launch your [AWS CloudShell](https://docs.aws.amazon.com/cloudshell/latest/userguide/getting-started.html#launch-region-shell) - use may use any region

![Launch CloudShell](https://d39bs20xyg7k53.cloudfront.net/services-screener/p1-cloudshell.gif)

## Installing service-screener
In the AWS CloudShell terminal, run this to install php:
```bash
if(sudo yum list installed  | grep php-cli > /dev/null) then echo 'PHP installed ,skipped'; else sudo amazon-linux-extras install -y php7.2; fi
## aws-sdk requires mbstring and xml
if(sudo yum list installed | grep php-mbstring > /dev/null) then echo 'php-mbstring installed, skipped'; else sudo yum install php-mbstring -y; fi
if(sudo yum list installed | grep php-xml > /dev/null) then echo 'php-xml installed, skipped'; else sudo yum install php-xml -y; fi
## remove existing old version of screener
rm -rf service-screener
git clone https://github.com/aws-samples/service-screener.git
cd service-screener 

## Install Composer & PHP SDK
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('sha384', 'composer-setup.php') === '906a84df04cea2aa72f40b5f787e49f22d4c2f19492ac310e8cba5b96ac8b64115ac402c8cd292b8a03482574915d1a8') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
php composer-setup.php
php -r "unlink('composer-setup.php');"
php -d memory_limit=-1 composer.phar require aws/aws-sdk-php
```

![Install dependencies](https://d39bs20xyg7k53.cloudfront.net/services-screener/p2-dependencies.gif)

## Using service-screener
```bash
alias screener='php $(pwd)/screen.php'
## screener --region <REGION_NAME(S)> --services <SERVICE_NAME(S)>
```

When running screener, you can specify the regions you want it to run on, as well as the service(s) you want. Currently, you can choose to run it on Amazon EC2, Amazon RDS, AWS IAM and Amazon S3. 
See below for examples:
```bash
## Singapore region only, and Amazon S3 service only
screener --region ap-southeast-1 --services s3

## Both Singapore & N. Virginia region with all services (Amazon EC2, AWS IAM, Amazon RDS, & Amazon S3 for now)
screener --region ap-southeast-1,us-east-1

## Both Singapore & N. Virginia region with Amazon RDS & AWS IAM
screener --region ap-southeast-1,us-east-1 --services rds,iam
```

![Get Report](https://d39bs20xyg7k53.cloudfront.net/services-screener/p3-getreport.gif)

The output is generated as an output.zip file. 
You can [download the file](https://docs.aws.amazon.com/cloudshell/latest/userguide/working-with-cloudshell.html#files-storage) in the CloudShell console. 

![Download Output](https://d39bs20xyg7k53.cloudfront.net/services-screener/p4-outputzip.gif)

Once downloaded, unzip the file and open 'index.html' in your browser. You should see a page like this:

![front page](https://d39bs20xyg7k53.cloudfront.net/services-screener/screener.jpg)

Ensure that you can see the service(s) run on listed on the left pane.
You can navigate to the service(s) listed to see detailed findings on each service. 

![Sample Output](https://d39bs20xyg7k53.cloudfront.net/services-screener/p5-sample.gif)

## Contributing to aws-screener
We encourage public contributions! Please review [CONTRIBUTING](./CONTRIBUTING.md) for details on our code of conduct and development process.

## Contact
Please review [CONTRIBUTING](./CONTRIBUTING.md) to raise any issues. 
You can view our GitHub profiles below:
[KuetTai](https://github.com/KuetTai)
[Sarika](https://github.com/sarika-subram)

## Security

See [CONTRIBUTING](CONTRIBUTING.md#security-issue-notifications) for more information.

## License

This project is licensed under the Apache-2.0 License.

