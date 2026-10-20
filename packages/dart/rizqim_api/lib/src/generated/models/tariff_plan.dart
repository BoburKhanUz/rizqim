// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'store_subscription.dart';

part 'tariff_plan.g.dart';

@JsonSerializable()
class TariffPlan {
  const TariffPlan({
    required this.id,
    required this.name,
    required this.code,
    required this.isActive,
    required this.commissionPercent,
    required this.monthlyBaseFee,
    required this.monthlyFeePerTerminal,
    required this.includedTerminals,
    required this.trialDays,
    required this.description,
    required this.createdAt,
    required this.updatedAt,
    this.subscriptions,
  });

  factory TariffPlan.fromJson(Map<String, Object?> json) =>
      _$TariffPlanFromJson(json);

  final int id;
  final String name;
  final String code;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'commission_percent')
  final String? commissionPercent;
  @JsonKey(name: 'monthly_base_fee')
  final String? monthlyBaseFee;
  @JsonKey(name: 'monthly_fee_per_terminal')
  final String? monthlyFeePerTerminal;
  @JsonKey(name: 'included_terminals')
  final int includedTerminals;
  @JsonKey(name: 'trial_days')
  final int trialDays;
  final String? description;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  final List<StoreSubscription>? subscriptions;

  Map<String, Object?> toJson() => _$TariffPlanToJson(this);
}
