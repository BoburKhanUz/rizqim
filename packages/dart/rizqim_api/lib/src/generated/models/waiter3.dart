// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'waiter3.g.dart';

@JsonSerializable()
class Waiter3 {
  const Waiter3({required this.id, required this.name});

  factory Waiter3.fromJson(Map<String, Object?> json) =>
      _$Waiter3FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Waiter3ToJson(this);
}
