// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by5.g.dart';

@JsonSerializable()
class By5 {
  const By5({required this.id, required this.name});

  factory By5.fromJson(Map<String, Object?> json) => _$By5FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$By5ToJson(this);
}
