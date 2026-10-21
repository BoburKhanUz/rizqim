// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'courier2.g.dart';

@JsonSerializable()
class Courier2 {
  const Courier2({required this.id, required this.name, required this.phone});

  factory Courier2.fromJson(Map<String, Object?> json) =>
      _$Courier2FromJson(json);

  final int id;
  final String? name;
  final String? phone;

  Map<String, Object?> toJson() => _$Courier2ToJson(this);
}
