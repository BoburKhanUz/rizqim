// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data249.g.dart';

@JsonSerializable()
class Data249 {
  const Data249({required this.requestId, required this.claimed});

  factory Data249.fromJson(Map<String, Object?> json) =>
      _$Data249FromJson(json);

  @JsonKey(name: 'request_id')
  final String requestId;
  final bool claimed;

  Map<String, Object?> toJson() => _$Data249ToJson(this);
}
