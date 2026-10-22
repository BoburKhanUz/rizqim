// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'lines9.g.dart';

@JsonSerializable()
class Lines9 {
  const Lines9({
    required this.name,
    required this.mxikCode,
    required this.packageCode,
    required this.unit,
    required this.quantity,
    required this.unitPrice,
    required this.amount,
    required this.vatRate,
    required this.vatAmount,
    required this.noVat,
    required this.commissionerTin,
    required this.markingCode,
  });

  factory Lines9.fromJson(Map<String, Object?> json) => _$Lines9FromJson(json);

  final String name;
  @JsonKey(name: 'mxik_code')
  final String mxikCode;
  @JsonKey(name: 'package_code')
  final String? packageCode;
  final String unit;
  final String quantity;
  @JsonKey(name: 'unit_price')
  final String unitPrice;
  final String amount;
  @JsonKey(name: 'vat_rate')
  final String vatRate;
  @JsonKey(name: 'vat_amount')
  final String vatAmount;
  @JsonKey(name: 'no_vat')
  final bool noVat;
  @JsonKey(name: 'commissioner_tin')
  final String? commissionerTin;
  @JsonKey(name: 'marking_code')
  final String? markingCode;

  Map<String, Object?> toJson() => _$Lines9ToJson(this);
}
