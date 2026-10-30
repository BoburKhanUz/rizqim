// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order_types2.dart';
import 'rows2.dart';

part 'data114.g.dart';

@JsonSerializable()
class Data114 {
  const Data114({
    required this.branchId,
    required this.enabled,
    required this.locked,
    required this.orderTypes,
    required this.rows,
  });

  factory Data114.fromJson(Map<String, Object?> json) =>
      _$Data114FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final bool enabled;
  final bool locked;
  @JsonKey(name: 'order_types')
  final List<OrderTypes2> orderTypes;
  final List<Rows2> rows;

  Map<String, Object?> toJson() => _$Data114ToJson(this);
}
