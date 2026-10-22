// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'hall7.g.dart';

@JsonSerializable()
class Hall7 {
  const Hall7({required this.id, required this.name});

  factory Hall7.fromJson(Map<String, Object?> json) => _$Hall7FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Hall7ToJson(this);
}
