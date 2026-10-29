// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data128.g.dart';

@JsonSerializable()
class Data128 {
  const Data128({required this.path, required this.url});

  factory Data128.fromJson(Map<String, Object?> json) =>
      _$Data128FromJson(json);

  final String path;
  final String url;

  Map<String, Object?> toJson() => _$Data128ToJson(this);
}
