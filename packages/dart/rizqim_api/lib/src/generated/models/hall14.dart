// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'hall14.g.dart';

@JsonSerializable()
class Hall14 {
  const Hall14({required this.id, required this.name});

  factory Hall14.fromJson(Map<String, Object?> json) => _$Hall14FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Hall14ToJson(this);
}
