// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order_types2.dart';
import 'rows3.dart';

part 'data160.g.dart';

@JsonSerializable()
class Data160 {
  const Data160({
    required this.branchId,
    required this.enabled,
    required this.locked,
    required this.orderTypes,
    required this.rows,
  });

  factory Data160.fromJson(Map<String, Object?> json) =>
      _$Data160FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final bool enabled;
  final bool locked;
  @JsonKey(name: 'order_types')
  final List<OrderTypes2> orderTypes;
  final List<Rows3> rows;

  Map<String, Object?> toJson() => _$Data160ToJson(this);
}
