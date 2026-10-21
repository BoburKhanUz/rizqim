// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'purpose.dart';
import 'connection.dart';
import 'paper_width.dart';

part 'v1_owner_printers_id_request_body.g.dart';

@JsonSerializable()
class V1OwnerPrintersIdRequestBody {
  const V1OwnerPrintersIdRequestBody({
    this.name,
    this.purpose,
    this.connection,
    this.address,
    this.port,
    this.paperWidth,
    this.stationId,
    this.deviceId,
    this.isActive,
    this.encoding,
    this.printAsImage,
  });

  factory V1OwnerPrintersIdRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerPrintersIdRequestBodyFromJson(json);

  final String? name;
  final Purpose? purpose;
  final Connection? connection;
  final String? address;
  final int? port;
  @JsonKey(name: 'paper_width')
  final PaperWidth? paperWidth;
  @JsonKey(name: 'station_id')
  final int? stationId;
  @JsonKey(name: 'device_id')
  final String? deviceId;
  @JsonKey(name: 'is_active')
  final bool? isActive;
  final String? encoding;
  @JsonKey(name: 'print_as_image')
  final bool? printAsImage;

  Map<String, Object?> toJson() => _$V1OwnerPrintersIdRequestBodyToJson(this);
}
