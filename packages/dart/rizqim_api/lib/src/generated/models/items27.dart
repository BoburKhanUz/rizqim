// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'items27.g.dart';

@JsonSerializable()
class Items27 {
  const Items27({required this.orderItemId, required this.quantity});

  factory Items27.fromJson(Map<String, Object?> json) =>
      _$Items27FromJson(json);

  @JsonKey(name: 'order_item_id')
  final int orderItemId;
  final num quantity;

  Map<String, Object?> toJson() => _$Items27ToJson(this);
}
