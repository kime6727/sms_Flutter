import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../core/theme/app_theme.dart';
import '../../core/i18n/app_localizations.dart';
import '../../core/utils/storage_service.dart';

class OnboardingScreen extends StatefulWidget {
  const OnboardingScreen({super.key});

  @override
  State<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends State<OnboardingScreen> {
  final PageController _pageController = PageController();
  int _currentPage = 0;

  final List<OnboardingPage> _pages = [
    OnboardingPage(
      icon: Icons.phone_android_outlined,
      title: '获取虚拟号码',
      titleEn: 'Get Virtual Numbers',
      description: '从全球100+国家中选择号码，接收短信验证码',
      descriptionEn: 'Choose numbers from 100+ countries worldwide to receive SMS verification codes',
    ),
    OnboardingPage(
      icon: Icons.flash_on,
      title: '即时接收',
      titleEn: 'Instant Reception',
      description: '快速接收各类平台验证码，支持批量购买',
      descriptionEn: 'Quickly receive verification codes from various platforms, support batch purchase',
    ),
    OnboardingPage(
      icon: Icons.shield_outlined,
      title: '保护隐私',
      titleEn: 'Protect Privacy',
      description: '使用虚拟号码保护您的真实手机号，安全无忧',
      descriptionEn: 'Use virtual numbers to protect your real phone number, safe and worry-free',
    ),
    OnboardingPage(
      icon: Icons.star_border,
      title: '超值优惠',
      titleEn: 'Great Value',
      description: '新用户注册即送积分，首充双倍积分优惠',
      descriptionEn: 'New users get bonus credits, double credits on first top-up',
    ),
  ];

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  void _nextPage() {
    if (_currentPage < _pages.length - 1) {
      _pageController.nextPage(
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeInOut,
      );
    } else {
      _completeOnboarding();
    }
  }

  void _skipOnboarding() {
    _completeOnboarding();
  }

  Future<void> _completeOnboarding() async {
    await StorageService.setBool('onboarding_completed', true);
    if (mounted) {
      context.go('/login');
    }
  }

  @override
  Widget build(BuildContext context) {
    final loc = context.loc;

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            Align(
              alignment: Alignment.topRight,
              child: TextButton(
                onPressed: _skipOnboarding,
                child: Text(
                  loc.translate('skip'),
                  style: const TextStyle(fontSize: 16),
                ),
              ),
            ),
            Expanded(
              child: PageView.builder(
                controller: _pageController,
                onPageChanged: (index) {
                  setState(() {
                    _currentPage = index;
                  });
                },
                itemCount: _pages.length,
                itemBuilder: (context, index) {
                  return _buildPage(_pages[index]);
                },
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: List.generate(
                      _pages.length,
                      (index) => _buildDot(index),
                    ),
                  ),
                  const SizedBox(height: 32),
                  SizedBox(
                    width: double.infinity,
                    height: 56,
                    child: ElevatedButton(
                      onPressed: _nextPage,
                      style: ElevatedButton.styleFrom(
                        textStyle: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      child: Text(
                        _currentPage == _pages.length - 1
                            ? loc.translate('done')
                            : loc.translate('next'),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPage(OnboardingPage page) {
    final loc = context.loc;
    final isEn = loc.locale.languageCode == 'en';
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 32),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            width: 160,
            height: 160,
            decoration: BoxDecoration(
              color: AppColors.primary.withOpacity(0.1),
              borderRadius: BorderRadius.circular(40),
            ),
            child: Icon(
              page.icon,
              size: 80,
              color: AppColors.primary,
            ),
          ),
          const SizedBox(height: 40),
          Text(
            isEn ? page.titleEn : page.title,
            style: const TextStyle(
              fontSize: 28,
              fontWeight: FontWeight.bold,
            ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 16),
          Text(
            isEn ? page.descriptionEn : page.description,
            style: TextStyle(
              fontSize: 16,
              color: Theme.of(context).colorScheme.onSurface.withOpacity(0.6),
              height: 1.6,
            ),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }

  Widget _buildDot(int index) {
    return AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      margin: const EdgeInsets.symmetric(horizontal: 4),
      width: _currentPage == index ? 24 : 8,
      height: 8,
      decoration: BoxDecoration(
        color: _currentPage == index ? AppColors.primary : AppColors.primary.withOpacity(0.2),
        borderRadius: BorderRadius.circular(4),
      ),
    );
  }
}

class OnboardingPage {
  final IconData icon;
  final String title;
  final String titleEn;
  final String description;
  final String descriptionEn;

  OnboardingPage({
    required this.icon,
    required this.title,
    required this.titleEn,
    required this.description,
    required this.descriptionEn,
  });
}
