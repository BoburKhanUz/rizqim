// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'member2.dart';
import 'month.dart';

part 'usage.g.dart';

@JsonSerializable()
class Usage {
  const Usage({
    required this.businessDate,
    required this.member,
    required this.month,
    required this.creditAvailable,
  });

  factory Usage.fromJson(Map<String, Object?> json) => _$UsageFromJson(json);

  @JsonKey(name: 'business_date')
  final String businessDate;
  final Member2? member;
  final Month month;
  @JsonKey(name: 'credit_available')
  final String? creditAvailable;

  Map<String, Object?> toJson() => _$UsageToJson(this);
}
