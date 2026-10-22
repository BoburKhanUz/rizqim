// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'waiter12.g.dart';

@JsonSerializable()
class Waiter12 {
  const Waiter12({required this.id, required this.name});

  factory Waiter12.fromJson(Map<String, Object?> json) =>
      _$Waiter12FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Waiter12ToJson(this);
}
