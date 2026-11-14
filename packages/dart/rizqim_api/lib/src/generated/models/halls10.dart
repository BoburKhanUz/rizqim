// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'halls10.g.dart';

@JsonSerializable()
class Halls10 {
  const Halls10({required this.id, required this.name});

  factory Halls10.fromJson(Map<String, Object?> json) =>
      _$Halls10FromJson(json);

  final int id;
  final String name;

  Map<String, Object?> toJson() => _$Halls10ToJson(this);
}
