// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'courier5.g.dart';

@JsonSerializable()
class Courier5 {
  const Courier5({required this.id, required this.name, required this.phone});

  factory Courier5.fromJson(Map<String, Object?> json) =>
      _$Courier5FromJson(json);

  final int id;
  final String? name;
  final String? phone;

  Map<String, Object?> toJson() => _$Courier5ToJson(this);
}
