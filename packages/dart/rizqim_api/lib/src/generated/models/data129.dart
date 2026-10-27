// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data129.g.dart';

@JsonSerializable()
class Data129 {
  const Data129({required this.path, required this.url});

  factory Data129.fromJson(Map<String, Object?> json) =>
      _$Data129FromJson(json);

  final String path;
  final String url;

  Map<String, Object?> toJson() => _$Data129ToJson(this);
}
