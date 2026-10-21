// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'order7.g.dart';

@JsonSerializable()
class Order7 {
  const Order7({required this.uuid, required this.status});

  factory Order7.fromJson(Map<String, Object?> json) => _$Order7FromJson(json);

  final String uuid;
  final String status;

  Map<String, Object?> toJson() => _$Order7ToJson(this);
}
