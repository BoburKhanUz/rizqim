// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'customer4.g.dart';

@JsonSerializable()
class Customer4 {
  const Customer4({required this.name, required this.phone});

  factory Customer4.fromJson(Map<String, Object?> json) =>
      _$Customer4FromJson(json);

  final String? name;
  final String? phone;

  Map<String, Object?> toJson() => _$Customer4ToJson(this);
}
