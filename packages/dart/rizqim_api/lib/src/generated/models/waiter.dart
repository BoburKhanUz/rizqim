// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'waiter.g.dart';

@JsonSerializable()
class Waiter {
  const Waiter({required this.id, required this.name});

  factory Waiter.fromJson(Map<String, Object?> json) => _$WaiterFromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$WaiterToJson(this);
}
