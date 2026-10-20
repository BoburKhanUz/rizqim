// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'image_thumb_urls.g.dart';

@JsonSerializable()
class ImageThumbUrls {
  const ImageThumbUrls({required this.sm, required this.md});

  factory ImageThumbUrls.fromJson(Map<String, Object?> json) =>
      _$ImageThumbUrlsFromJson(json);

  final String? sm;
  final String? md;

  Map<String, Object?> toJson() => _$ImageThumbUrlsToJson(this);
}
