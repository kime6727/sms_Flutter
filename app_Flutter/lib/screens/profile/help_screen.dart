import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../core/theme/app_theme.dart';
import '../../core/i18n/app_localizations.dart';
import '../../core/config/app_config.dart';

class HelpScreen extends StatelessWidget {
  const HelpScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final loc = context.loc;

    return Scaffold(
      appBar: AppBar(
        title: Text(loc.translate('faq')),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: ListTile(
              leading: Icon(Icons.help_outline, color: AppColors.primary),
              title: Text(loc.translate('help_center')),
              subtitle: Text(loc.translate('visit_help_center')),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => _launchUrl(AppConfig.helpUrl),
            ),
          ),
          const SizedBox(height: 16),
          const FAQItem(
            question: '如何购买虚拟号码？',
            questionEn: 'How to buy a virtual number?',
            answer: '1. 在首页选择您需要的服务\n2. 选择国家\n3. 确认购买\n4. 使用积分支付',
            answerEn: '1. Select the service you need on the home page\n2. Choose a country\n3. Confirm purchase\n4. Pay with credits',
          ),
          FAQItem(
            question: '如何充值积分？',
            questionEn: 'How to top up credits?',
            answer: '进入"我的"页面，点击余额旁的"充值"按钮，选择套餐完成支付。新用户首充可享受双倍积分优惠。',
            answerEn: 'Go to "Profile" page, click the "Top Up" button next to balance, select a package and complete payment. New users can enjoy double credits on first top-up.',
          ),
          FAQItem(
            question: '号码有效期是多久？',
            questionEn: 'How long is the number valid?',
            answer: '号码激活后通常有20分钟的有效期。在有效期内您可以接收短信验证码。',
            answerEn: 'Numbers are typically valid for 20 minutes after activation. You can receive SMS verification codes within this period.',
          ),
          FAQItem(
            question: '为什么没有收到验证码？',
            questionEn: 'Why didn\'t I receive the verification code?',
            answer: '可能原因：\n1. 平台发送延迟\n2. 号码已被其他人使用\n3. 服务暂时不可用\n\n建议：等待几分钟后刷新，或重新购买号码。',
            answerEn: 'Possible reasons:\n1. Platform sending delay\n2. Number already used by others\n3. Service temporarily unavailable\n\nSuggestion: Wait a few minutes and refresh, or purchase a new number.',
          ),
          FAQItem(
            question: '积分可以退款吗？',
            questionEn: 'Can credits be refunded?',
            answer: '已购买的号码如果未能成功接收短信，系统会自动退款。充值积分一般不支持退款，特殊情况请联系客服。',
            answerEn: 'If a purchased number fails to receive SMS, the system will automatically refund. Top-up credits are generally non-refundable, please contact support for special cases.',
          ),
          FAQItem(
            question: '如何联系客服？',
            questionEn: 'How to contact support?',
            answer: '您可以通过以下方式联系我们：\n1. 应用内"联系客服"功能\n2. 访问帮助中心\n3. 发送邮件至 support@niceapps.com',
            answerEn: 'You can contact us through:\n1. In-app "Contact Us" feature\n2. Visit Help Center\n3. Email: support@niceapps.com',
          ),
          FAQItem(
            question: '支持哪些国家的号码？',
            questionEn: 'Which countries are supported?',
            answer: '我们支持全球100+个国家的号码，包括美国、英国、中国、印度、俄罗斯等。具体可在首页查看可用国家列表。',
            answerEn: 'We support numbers from 100+ countries worldwide, including USA, UK, China, India, Russia, etc. You can check the available country list on the home page.',
          ),
        ],
      ),
    );
  }

  Future<void> _launchUrl(String url) async {
    final uri = Uri.parse(url);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    } else {
      debugPrint('Cannot launch $url');
    }
  }
}

class FAQItem extends StatefulWidget {
  final String question;
  final String questionEn;
  final String answer;
  final String answerEn;

  const FAQItem({
    super.key,
    required this.question,
    required this.questionEn,
    required this.answer,
    required this.answerEn,
  });

  @override
  State<FAQItem> createState() => _FAQItemState();
}

class _FAQItemState extends State<FAQItem> {
  bool _isExpanded = false;

  @override
  Widget build(BuildContext context) {
    final loc = context.loc;
    final isChinese = loc.locale.languageCode == 'zh';

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ExpansionTile(
        title: Text(
          isChinese ? widget.question : widget.questionEn,
          style: const TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w500,
          ),
        ),
        trailing: Icon(
          _isExpanded ? Icons.expand_less : Icons.expand_more,
          color: AppColors.primary,
        ),
        onExpansionChanged: (expanded) {
          setState(() {
            _isExpanded = expanded;
          });
        },
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
            child: Text(
              isChinese ? widget.answer : widget.answerEn,
              style: TextStyle(
                fontSize: 14,
                color: Theme.of(context).colorScheme.onSurface.withOpacity(0.7),
                height: 1.6,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
