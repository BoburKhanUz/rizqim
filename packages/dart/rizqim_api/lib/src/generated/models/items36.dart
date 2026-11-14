// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'items36.g.dart';

@JsonSerializable()
class Items36 {
  const Items36({required this.orderItemId, required this.quantity});

  factory Items36.fromJson(Map<String, Object?> json) =>
      _$Items36FromJson(json);

  @JsonKey(name: 'order_item_id')
  final int orderItemId;
  final num quantity;

  Map<String, Object?> toJson() => _$Items36ToJson(this);
}
