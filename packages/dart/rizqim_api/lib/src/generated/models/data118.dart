// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'period3.dart';
import 'products4.dart';

part 'data118.g.dart';

@JsonSerializable()
class Data118 {
  const Data118({
    required this.period,
    required this.branchId,
    required this.sort,
    required this.products,
  });

  factory Data118.fromJson(Map<String, Object?> json) =>
      _$Data118FromJson(json);

  final Period3 period;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  final String sort;
  final List<Products4> products;

  Map<String, Object?> toJson() => _$Data118ToJson(this);
}
