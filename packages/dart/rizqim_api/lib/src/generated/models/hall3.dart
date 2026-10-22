// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'hall3.g.dart';

@JsonSerializable()
class Hall3 {
  const Hall3({required this.id, required this.name});

  factory Hall3.fromJson(Map<String, Object?> json) => _$Hall3FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Hall3ToJson(this);
}
