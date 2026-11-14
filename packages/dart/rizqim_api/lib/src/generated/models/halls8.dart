// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'halls8.g.dart';

@JsonSerializable()
class Halls8 {
  const Halls8({required this.id, required this.name});

  factory Halls8.fromJson(Map<String, Object?> json) => _$Halls8FromJson(json);

  final int id;
  final String name;

  Map<String, Object?> toJson() => _$Halls8ToJson(this);
}
