// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'tills.dart';

part 'data103.g.dart';

@JsonSerializable()
class Data103 {
  const Data103({
    required this.driver,
    required this.tin,
    required this.tinSet,
    required this.tills,
  });

  factory Data103.fromJson(Map<String, Object?> json) =>
      _$Data103FromJson(json);

  final String? driver;
  final String? tin;
  @JsonKey(name: 'tin_set')
  final bool tinSet;
  final List<Tills> tills;

  Map<String, Object?> toJson() => _$Data103ToJson(this);
}
