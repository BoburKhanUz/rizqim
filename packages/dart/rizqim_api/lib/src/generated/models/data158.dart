// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order_types2.dart';
import 'rows3.dart';

part 'data158.g.dart';

@JsonSerializable()
class Data158 {
  const Data158({
    required this.branchId,
    required this.enabled,
    required this.locked,
    required this.orderTypes,
    required this.rows,
  });

  factory Data158.fromJson(Map<String, Object?> json) =>
      _$Data158FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final bool enabled;
  final bool locked;
  @JsonKey(name: 'order_types')
  final List<OrderTypes2> orderTypes;
  final List<Rows3> rows;

  Map<String, Object?> toJson() => _$Data158ToJson(this);
}
