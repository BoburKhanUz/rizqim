// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'waiter4.g.dart';

@JsonSerializable()
class Waiter4 {
  const Waiter4({required this.id, required this.name});

  factory Waiter4.fromJson(Map<String, Object?> json) =>
      _$Waiter4FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Waiter4ToJson(this);
}
