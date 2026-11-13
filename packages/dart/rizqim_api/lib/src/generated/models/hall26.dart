// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'hall26.g.dart';

@JsonSerializable()
class Hall26 {
  const Hall26({required this.id, required this.name});

  factory Hall26.fromJson(Map<String, Object?> json) => _$Hall26FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Hall26ToJson(this);
}
