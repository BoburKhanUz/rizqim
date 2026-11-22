// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'period3.dart';
import 'products8.dart';
import 'combo_parts.dart';

part 'data188.g.dart';

@JsonSerializable()
class Data188 {
  const Data188({
    required this.period,
    required this.branchId,
    required this.sort,
    required this.products,
    required this.comboParts,
  });

  factory Data188.fromJson(Map<String, Object?> json) =>
      _$Data188FromJson(json);

  final Period3 period;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  final String sort;
  final List<Products8> products;
  @JsonKey(name: 'combo_parts')
  final List<ComboParts> comboParts;

  Map<String, Object?> toJson() => _$Data188ToJson(this);
}
