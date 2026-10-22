// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'layout.g.dart';

@JsonSerializable()
class Layout {
  const Layout({
    required this.x,
    required this.y,
    required this.w,
    required this.h,
  });

  factory Layout.fromJson(Map<String, Object?> json) => _$LayoutFromJson(json);

  final String? x;
  final String? y;
  final String? w;
  final String? h;

  Map<String, Object?> toJson() => _$LayoutToJson(this);
}
