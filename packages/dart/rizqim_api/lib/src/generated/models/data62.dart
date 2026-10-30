// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'parts.dart';
import 'combo_fiscal_mode.dart';
import 'fiscal.dart';

part 'data62.g.dart';

@JsonSerializable()
class Data62 {
  const Data62({
    required this.name,
    required this.quantity,
    required this.basePrice,
    required this.delta,
    required this.price,
    required this.parts,
    required this.fiscalMode,
    required this.fiscal,
  });

  factory Data62.fromJson(Map<String, Object?> json) => _$Data62FromJson(json);

  final String name;
  final int quantity;
  @JsonKey(name: 'base_price')
  final String basePrice;
  final String delta;
  final String price;
  final List<Parts> parts;
  @JsonKey(name: 'fiscal_mode')
  final ComboFiscalMode fiscalMode;
  final Fiscal fiscal;

  Map<String, Object?> toJson() => _$Data62ToJson(this);
}
