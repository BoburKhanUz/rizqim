// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'waiter15.g.dart';

@JsonSerializable()
class Waiter15 {
  const Waiter15({required this.id, required this.name});

  factory Waiter15.fromJson(Map<String, Object?> json) =>
      _$Waiter15FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Waiter15ToJson(this);
}
