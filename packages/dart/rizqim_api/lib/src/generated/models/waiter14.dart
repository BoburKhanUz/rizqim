// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'waiter14.g.dart';

@JsonSerializable()
class Waiter14 {
  const Waiter14({required this.id, required this.name});

  factory Waiter14.fromJson(Map<String, Object?> json) =>
      _$Waiter14FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Waiter14ToJson(this);
}
