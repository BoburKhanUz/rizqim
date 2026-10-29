// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'waiter24.g.dart';

@JsonSerializable()
class Waiter24 {
  const Waiter24({required this.id, required this.name});

  factory Waiter24.fromJson(Map<String, Object?> json) =>
      _$Waiter24FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Waiter24ToJson(this);
}
