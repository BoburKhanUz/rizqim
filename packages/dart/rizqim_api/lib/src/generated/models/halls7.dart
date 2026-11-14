// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'halls7.g.dart';

@JsonSerializable()
class Halls7 {
  const Halls7({required this.id, required this.name});

  factory Halls7.fromJson(Map<String, Object?> json) => _$Halls7FromJson(json);

  final int id;
  final String name;

  Map<String, Object?> toJson() => _$Halls7ToJson(this);
}
