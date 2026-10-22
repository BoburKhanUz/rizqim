// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'waiter13.g.dart';

@JsonSerializable()
class Waiter13 {
  const Waiter13({required this.id, required this.name});

  factory Waiter13.fromJson(Map<String, Object?> json) =>
      _$Waiter13FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Waiter13ToJson(this);
}
