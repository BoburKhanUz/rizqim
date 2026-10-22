// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'customer5.g.dart';

@JsonSerializable()
class Customer5 {
  const Customer5({required this.name, required this.phone});

  factory Customer5.fromJson(Map<String, Object?> json) =>
      _$Customer5FromJson(json);

  final String? name;
  final String? phone;

  Map<String, Object?> toJson() => _$Customer5ToJson(this);
}
