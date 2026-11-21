// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'releases.g.dart';

@JsonSerializable()
class Releases {
  const Releases({
    required this.app,
    required this.platform,
    required this.version,
    required this.build,
    required this.fileUrl,
    required this.sha256,
    required this.sizeBytes,
    required this.notes,
    required this.releasedAt,
    required this.isMinimum,
    required this.minimumFrom,
    required this.isLatest,
  });

  factory Releases.fromJson(Map<String, Object?> json) =>
      _$ReleasesFromJson(json);

  final String app;
  final String platform;
  final String version;
  final int build;
  @JsonKey(name: 'file_url')
  final String fileUrl;
  final String sha256;
  @JsonKey(name: 'size_bytes')
  final int sizeBytes;
  final String? notes;
  @JsonKey(name: 'released_at')
  final String releasedAt;
  @JsonKey(name: 'is_minimum')
  final bool isMinimum;
  @JsonKey(name: 'minimum_from')
  final String? minimumFrom;
  @JsonKey(name: 'is_latest')
  final bool isLatest;

  Map<String, Object?> toJson() => _$ReleasesToJson(this);
}
