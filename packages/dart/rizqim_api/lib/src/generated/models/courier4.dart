// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'courier4.g.dart';

@JsonSerializable()
class Courier4 {
  const Courier4({required this.id, required this.name, required this.phone});

  factory Courier4.fromJson(Map<String, Object?> json) =>
      _$Courier4FromJson(json);

  final int id;
  final String? name;
  final String? phone;

  Map<String, Object?> toJson() => _$Courier4ToJson(this);
}
