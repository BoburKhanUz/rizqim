// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_fiscal_uzqr_tills_uuid_request_body.g.dart';

@JsonSerializable()
class V1OwnerFiscalUzqrTillsUuidRequestBody {
  const V1OwnerFiscalUzqrTillsUuidRequestBody({this.apiKey, this.removeApiKey});

  factory V1OwnerFiscalUzqrTillsUuidRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerFiscalUzqrTillsUuidRequestBodyFromJson(json);

  /// Kassaning UzQR API kaliti (yashirin; maskalangan qiymat saqlanganini o'zgartirmaydi).
  @JsonKey(name: 'api_key')
  final String? apiKey;
  @JsonKey(name: 'remove_api_key')
  final bool? removeApiKey;

  Map<String, Object?> toJson() =>
      _$V1OwnerFiscalUzqrTillsUuidRequestBodyToJson(this);
}
