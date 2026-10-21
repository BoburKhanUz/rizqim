// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'hall6.g.dart';

@JsonSerializable()
class Hall6 {
  const Hall6({required this.id, required this.name});

  factory Hall6.fromJson(Map<String, Object?> json) => _$Hall6FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Hall6ToJson(this);
}
