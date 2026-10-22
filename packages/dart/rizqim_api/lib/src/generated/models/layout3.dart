// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'layout3.g.dart';

@JsonSerializable()
class Layout3 {
  const Layout3({this.x, this.y, this.w, this.h});

  factory Layout3.fromJson(Map<String, Object?> json) =>
      _$Layout3FromJson(json);

  final num? x;
  final num? y;
  final num? w;
  final num? h;

  Map<String, Object?> toJson() => _$Layout3ToJson(this);
}
