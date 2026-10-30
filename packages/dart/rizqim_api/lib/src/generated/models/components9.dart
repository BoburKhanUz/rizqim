// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'components9.g.dart';

@JsonSerializable()
class Components9 {
  const Components9({
    required this.type,
    required this.label,
    required this.ruleUuid,
    required this.days,
    required this.quantity,
    required this.rate,
    required this.baseValue,
    required this.amount,
    required this.detail,
  });

  factory Components9.fromJson(Map<String, Object?> json) =>
      _$Components9FromJson(json);

  final String type;
  final String label;
  @JsonKey(name: 'rule_uuid')
  final String? ruleUuid;
  final int days;
  final String? quantity;
  final String? rate;

  /// The name has been replaced because it contains a keyword. Original name: `base`.
  /// The name has been replaced because it contains a keyword. Original name: `base`.
  /// The name has been replaced because it contains a keyword. Original name: `base`.
  @JsonKey(name: 'base')
  final String? baseValue;
  final String amount;
  final String detail;

  Map<String, Object?> toJson() => _$Components9ToJson(this);
}
