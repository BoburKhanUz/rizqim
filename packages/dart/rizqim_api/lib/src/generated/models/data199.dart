// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data199.g.dart';

@JsonSerializable()
class Data199 {
  const Data199({required this.path, required this.url});

  factory Data199.fromJson(Map<String, Object?> json) =>
      _$Data199FromJson(json);

  final String path;
  final String url;

  Map<String, Object?> toJson() => _$Data199ToJson(this);
}
