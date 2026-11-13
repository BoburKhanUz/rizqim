// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'waiter30.g.dart';

@JsonSerializable()
class Waiter30 {
  const Waiter30({required this.id, required this.name});

  factory Waiter30.fromJson(Map<String, Object?> json) =>
      _$Waiter30FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Waiter30ToJson(this);
}
