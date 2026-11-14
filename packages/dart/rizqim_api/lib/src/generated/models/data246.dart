// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'requests.dart';

part 'data246.g.dart';

@JsonSerializable()
class Data246 {
  const Data246({required this.requests});

  factory Data246.fromJson(Map<String, Object?> json) =>
      _$Data246FromJson(json);

  final List<Requests> requests;

  Map<String, Object?> toJson() => _$Data246ToJson(this);
}
