// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'period3.dart';
import 'products7.dart';
import 'combo_parts.dart';

part 'data147.g.dart';

@JsonSerializable()
class Data147 {
  const Data147({
    required this.period,
    required this.branchId,
    required this.sort,
    required this.products,
    required this.comboParts,
  });

  factory Data147.fromJson(Map<String, Object?> json) =>
      _$Data147FromJson(json);

  final Period3 period;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  final String sort;
  final List<Products7> products;
  @JsonKey(name: 'combo_parts')
  final List<ComboParts> comboParts;

  Map<String, Object?> toJson() => _$Data147ToJson(this);
}
