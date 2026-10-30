// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order_types.dart';
import 'rows.dart';

part 'data113.g.dart';

@JsonSerializable()
class Data113 {
  const Data113({
    required this.branchId,
    required this.enabled,
    required this.locked,
    required this.orderTypes,
    required this.rows,
  });

  factory Data113.fromJson(Map<String, Object?> json) =>
      _$Data113FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final bool enabled;
  final bool locked;
  @JsonKey(name: 'order_types')
  final List<OrderTypes> orderTypes;
  final List<Rows> rows;

  Map<String, Object?> toJson() => _$Data113ToJson(this);
}
