// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'halls9.g.dart';

@JsonSerializable()
class Halls9 {
  const Halls9({required this.id, required this.name});

  factory Halls9.fromJson(Map<String, Object?> json) => _$Halls9FromJson(json);

  final int id;
  final String name;

  Map<String, Object?> toJson() => _$Halls9ToJson(this);
}
