// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'service_charge_rules.g.dart';

@JsonSerializable()
class ServiceChargeRules {
  const ServiceChargeRules({
    required this.byOrderType,
    required this.largeGroupMinGuests,
    required this.largeGroupPercent,
    required this.coverAmount,
  });

  factory ServiceChargeRules.fromJson(Map<String, Object?> json) =>
      _$ServiceChargeRulesFromJson(json);

  @JsonKey(name: 'by_order_type')
  final Map<String, String> byOrderType;
  @JsonKey(name: 'large_group_min_guests')
  final int? largeGroupMinGuests;
  @JsonKey(name: 'large_group_percent')
  final String? largeGroupPercent;
  @JsonKey(name: 'cover_amount')
  final String? coverAmount;

  Map<String, Object?> toJson() => _$ServiceChargeRulesToJson(this);
}
