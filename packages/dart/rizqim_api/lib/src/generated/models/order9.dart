// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'order9.g.dart';

@JsonSerializable()
class Order9 {
  const Order9({required this.uuid, required this.status});

  factory Order9.fromJson(Map<String, Object?> json) => _$Order9FromJson(json);

  final String uuid;
  final String status;

  Map<String, Object?> toJson() => _$Order9ToJson(this);
}
