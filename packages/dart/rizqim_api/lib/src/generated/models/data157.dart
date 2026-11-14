// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order_types.dart';
import 'rows2.dart';

part 'data157.g.dart';

@JsonSerializable()
class Data157 {
  const Data157({
    required this.branchId,
    required this.enabled,
    required this.locked,
    required this.orderTypes,
    required this.rows,
  });

  factory Data157.fromJson(Map<String, Object?> json) =>
      _$Data157FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final bool enabled;
  final bool locked;
  @JsonKey(name: 'order_types')
  final List<OrderTypes> orderTypes;
  final List<Rows2> rows;

  Map<String, Object?> toJson() => _$Data157ToJson(this);
}
