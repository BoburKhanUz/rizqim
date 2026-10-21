// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'order2.g.dart';

@JsonSerializable()
class Order2 {
  const Order2({required this.uuid, required this.shortCode});

  factory Order2.fromJson(Map<String, Object?> json) => _$Order2FromJson(json);

  final String uuid;
  @JsonKey(name: 'short_code')
  final String? shortCode;

  Map<String, Object?> toJson() => _$Order2ToJson(this);
}
